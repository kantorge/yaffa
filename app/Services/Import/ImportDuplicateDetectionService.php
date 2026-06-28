<?php

namespace App\Services\Import;

use App\Enums\TransactionType;
use App\Models\Transaction;
use App\Models\User;
use App\Services\DuplicateDetectionService;
use Throwable;

class ImportDuplicateDetectionService
{
    public function __construct(private DuplicateDetectionService $duplicateDetectionService)
    {
    }

    /**
     * @param  list<array<string, mixed>>  $drafts
     * @return list<array<string, mixed>>
     */
    public function enrichDrafts(User $user, array $drafts): array
    {
        $settings = $this->duplicateDetectionService->resolveSettingsForUser($user);
        $dateWindowDays = max(1, (int) ($settings['duplicate_date_window_days'] ?? 3));
        $amountTolerancePercent = (float) ($settings['duplicate_amount_tolerance_percent'] ?? 10.0);
        $similarityThreshold = (float) ($settings['duplicate_similarity_threshold'] ?? 0.5);

        // Single bulk query covers all drafts — O(1) DB round-trips instead of O(n).
        $windowTransactions = $this->loadTransactionWindow($user, $drafts, $dateWindowDays);
        $windowById = $windowTransactions->keyBy('id');

        $enriched = [];

        foreach ($drafts as $draft) {
            $input = $this->toDuplicateDetectionInput($draft);

            if ($input === null) {
                $draft['duplicate_candidates'] = [];
                $enriched[] = $draft;
                continue;
            }

            try {
                $matches = $this->duplicateDetectionService->findDuplicatesFromWindow(
                    $input,
                    $windowTransactions,
                    $amountTolerancePercent,
                    $similarityThreshold,
                    $dateWindowDays,
                );
            } catch (Throwable) {
                $draft['duplicate_candidates'] = [];
                $draft['warnings'] = array_merge((array) ($draft['warnings'] ?? []), [
                    __('Duplicate detection skipped for this row due to an unexpected error.'),
                ]);
                $enriched[] = $draft;
                continue;
            }

            $draft['duplicate_candidates'] = collect($matches)
                ->take(10)
                ->map(function (array $match) use ($draft, $windowById): array {
                    $transactionId = (int) $match['id'];
                    /** @var Transaction|null $transaction */
                    $transaction = $windowById->get($transactionId);

                    $similarity = (float) $match['similarity'];

                    return [
                        'transaction_id' => $transactionId,
                        'confidence_score' => round($similarity, 3),
                        'similarity_score' => round($similarity, 3),
                        'matched_on' => $this->buildMatchedOnSignals($draft, $transaction),
                        'summary' => [
                            'date' => $transaction?->date?->format('Y-m-d'),
                            'comment' => $transaction?->comment,
                            'amount' => $transaction ? (float) $transaction->transactionItems->sum('amount') : null,
                        ],
                    ];
                })
                ->values()
                ->all();

            $enriched[] = $draft;
        }

        return $enriched;
    }

    /**
     * Load all transactions that fall within the combined date window of the given drafts.
     * Eager-loads config and transactionItems to prevent N+1 queries during scoring.
     *
     * @param  list<array<string, mixed>>  $drafts
     * @return \Illuminate\Database\Eloquent\Collection<int, Transaction>
     */
    private function loadTransactionWindow(User $user, array $drafts, int $dateWindowDays): \Illuminate\Database\Eloquent\Collection
    {
        $draftDates = collect($drafts)
            ->pluck('date')
            ->filter(fn (mixed $d): bool => is_string($d) && $d !== '')
            ->map(function (string $d): ?\Carbon\Carbon {
                try {
                    return \Carbon\Carbon::parse($d);
                } catch (Throwable) {
                    return null;
                }
            })
            ->filter();

        if ($draftDates->isEmpty()) {
            return new \Illuminate\Database\Eloquent\Collection();
        }

        $windowStart = $draftDates->min()->subDays($dateWindowDays);
        $windowEnd = $draftDates->max()->addDays($dateWindowDays);

        /** @var \Illuminate\Database\Eloquent\Collection<int, Transaction> $result */
        $result = $user->transactions()
            ->whereBetween('date', [$windowStart, $windowEnd])
            ->with(['config', 'transactionItems'])
            ->get();

        return $result;
    }

    /**
     * @param  array<string, mixed>  $draft
     * @return array<string, mixed>|null
     */
    private function toDuplicateDetectionInput(array $draft): ?array
    {
        if (! is_string($draft['date'] ?? null)) {
            return null;
        }

        try {
            \Carbon\Carbon::parse($draft['date']);
        } catch (\Carbon\Exceptions\InvalidFormatException) {
            return null;
        }

        $amount = $draft['amount'] ?? null;
        if (! is_numeric($amount)) {
            return null;
        }

        $transactionType = is_string($draft['transaction_type'] ?? null)
            ? $draft['transaction_type']
            : TransactionType::WITHDRAWAL->value;

        $configType = is_string($draft['config_type'] ?? null)
            ? $draft['config_type']
            : 'standard';

        $input = [
            'date' => $draft['date'],
            'amount' => (float) $amount,
            'config_type' => $configType,
        ];

        $accountFromId = data_get($draft, 'config.account_from_id');
        if (is_int($accountFromId)) {
            $input['account_from_id'] = $accountFromId;
        }

        $accountToId = data_get($draft, 'config.account_to_id');
        if (is_int($accountToId)) {
            $input['account_to_id'] = $accountToId;
        }

        if (! isset($input['account_to_id']) && $transactionType === TransactionType::WITHDRAWAL->value) {
            $matchedPayeeId = data_get($draft, 'matched_payee.id');
            if (is_int($matchedPayeeId)) {
                $input['account_to_id'] = $matchedPayeeId;
            }
        }

        if (! isset($input['account_from_id']) && $transactionType === TransactionType::DEPOSIT->value) {
            $matchedPayeeId = data_get($draft, 'matched_payee.id');
            if (is_int($matchedPayeeId)) {
                $input['account_from_id'] = $matchedPayeeId;
            }
        }

        return $input;
    }

    /**
     * @param  array<string, mixed>  $draft
     * @return list<string>
     */
    private function buildMatchedOnSignals(array $draft, ?Transaction $transaction): array
    {
        if (! $transaction instanceof Transaction) {
            return [];
        }

        $signals = ['date'];

        $draftAmount = is_numeric($draft['amount'] ?? null) ? (float) $draft['amount'] : null;
        $transactionAmount = (float) $transaction->transactionItems->sum('amount');

        if ($draftAmount !== null && abs($draftAmount - $transactionAmount) < 0.00001) {
            $signals[] = 'amount';
        }

        $draftFrom = data_get($draft, 'config.account_from_id');
        $draftTo = data_get($draft, 'config.account_to_id');

        if (is_int($draftFrom) && $transaction->isStandard() && (int) data_get($transaction->config, 'account_from_id') === $draftFrom) {
            $signals[] = 'account_from';
        }

        if (is_int($draftTo) && $transaction->isStandard() && (int) data_get($transaction->config, 'account_to_id') === $draftTo) {
            $signals[] = 'account_to';
        }

        return array_values(array_unique($signals));
    }
}
