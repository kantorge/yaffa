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
     * Enrich drafts with candidate scheduled transactions (schedule = true, with an active
     * TransactionSchedule) whose next_date falls within the same window that duplicate
     * detection uses for the transaction's own date. Uses the exact same scoring engine and
     * settings as enrichDrafts() — only the candidate pool and the date being compared against
     * (next_date instead of date) differ.
     *
     * @param  list<array<string, mixed>>  $drafts
     * @return list<array<string, mixed>>
     */
    public function enrichDraftsWithScheduleCandidates(User $user, array $drafts): array
    {
        $settings = $this->duplicateDetectionService->resolveSettingsForUser($user);
        $dateWindowDays = max(1, (int) ($settings['duplicate_date_window_days'] ?? 3));
        $amountTolerancePercent = (float) ($settings['duplicate_amount_tolerance_percent'] ?? 10.0);
        $similarityThreshold = (float) ($settings['duplicate_similarity_threshold'] ?? 0.5);

        $scheduleWindow = $this->loadScheduleWindow($user, $drafts, $dateWindowDays);
        $scheduleWindowById = $scheduleWindow->keyBy('id');

        $enriched = [];

        foreach ($drafts as $draft) {
            $input = $this->toDuplicateDetectionInput($draft);

            if ($input === null) {
                $draft['schedule_candidates'] = [];
                $enriched[] = $draft;
                continue;
            }

            try {
                $matches = $this->duplicateDetectionService->findDuplicatesFromWindow(
                    $input,
                    $scheduleWindow,
                    $amountTolerancePercent,
                    $similarityThreshold,
                    $dateWindowDays,
                    static fn (Transaction $transaction) => $transaction->transactionSchedule?->next_date,
                );
            } catch (Throwable) {
                $draft['schedule_candidates'] = [];
                $draft['warnings'] = array_merge((array) ($draft['warnings'] ?? []), [
                    __('Scheduled transaction matching skipped for this row due to an unexpected error.'),
                ]);
                $enriched[] = $draft;
                continue;
            }

            $draft['schedule_candidates'] = collect($matches)
                ->take(10)
                ->map(function (array $match) use ($draft, $scheduleWindowById): array {
                    $transactionId = (int) $match['id'];
                    /** @var Transaction|null $transaction */
                    $transaction = $scheduleWindowById->get($transactionId);

                    $similarity = (float) $match['similarity'];

                    return [
                        'transaction_id' => $transactionId,
                        'confidence_score' => round($similarity, 3),
                        'similarity_score' => round($similarity, 3),
                        'matched_on' => $this->buildMatchedOnSignals($draft, $transaction),
                        'summary' => [
                            'next_date' => $transaction?->transactionSchedule?->next_date?->format('Y-m-d'),
                            'comment' => $transaction?->comment,
                            'amount' => $transaction ? (float) $transaction->transactionItems->sum('amount') : null,
                            'frequency' => $transaction?->transactionSchedule?->frequency,
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
            ->select(['id', 'date', 'config_type', 'config_id'])
            ->with(['config', 'transactionItems'])
            ->get();

        return $result;
    }

    /**
     * Load all schedule-owning transactions (schedule = true, with an active TransactionSchedule)
     * whose next_date falls within the combined date window of the given drafts.
     * Eager-loads config, transactionItems and transactionSchedule to prevent N+1 queries during scoring.
     *
     * @param  list<array<string, mixed>>  $drafts
     * @return \Illuminate\Database\Eloquent\Collection<int, Transaction>
     */
    private function loadScheduleWindow(User $user, array $drafts, int $dateWindowDays): \Illuminate\Database\Eloquent\Collection
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
            ->where('schedule', true)
            ->whereHas('transactionSchedule', function ($query) use ($windowStart, $windowEnd): void {
                $query->where('active', true)
                    ->whereBetween('next_date', [$windowStart, $windowEnd]);
            })
            ->select(['id', 'date', 'config_type', 'config_id'])
            ->with(['config', 'transactionItems', 'transactionSchedule'])
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

        $configType = is_string($draft['config_type'] ?? null)
            ? $draft['config_type']
            : 'standard';

        $input = [
            'date' => $draft['date'],
            'amount' => (float) $amount,
            'config_type' => $configType,
        ];

        $resolved = $this->resolveDraftAccountIds($draft);

        if ($resolved['account_from_id'] !== null) {
            $input['account_from_id'] = $resolved['account_from_id'];
        }

        if ($resolved['account_to_id'] !== null) {
            $input['account_to_id'] = $resolved['account_to_id'];
        }

        return $input;
    }

    /**
     * Resolve the effective account_from_id/account_to_id used for comparison.
     *
     * Explicit parser-set config values (e.g. from a system CSV profile's matching rules)
     * take precedence. Otherwise, the identified payee is used as a fallback — regardless
     * of match confidence — on whichever side the transaction direction implies is the
     * counterparty. The `payee_from_matched`/`payee_to_matched` flags record which side (if
     * any) came from that fallback, so callers can label the signal as "payee" rather than
     * a generic account match.
     *
     * @param  array<string, mixed>  $draft
     * @return array{account_from_id: int|null, account_to_id: int|null, payee_from_matched: bool, payee_to_matched: bool}
     */
    private function resolveDraftAccountIds(array $draft): array
    {
        $transactionType = is_string($draft['transaction_type'] ?? null)
            ? $draft['transaction_type']
            : TransactionType::WITHDRAWAL->value;

        $accountFromId = data_get($draft, 'config.account_from_id');
        $accountFromId = is_int($accountFromId) ? $accountFromId : null;

        $accountToId = data_get($draft, 'config.account_to_id');
        $accountToId = is_int($accountToId) ? $accountToId : null;

        $payeeFromMatched = false;
        $payeeToMatched = false;

        $matchedPayeeId = data_get($draft, 'matched_payee.id');
        $matchedPayeeId = is_int($matchedPayeeId) ? $matchedPayeeId : null;

        if ($matchedPayeeId !== null) {
            if ($accountToId === null && $transactionType === TransactionType::WITHDRAWAL->value) {
                $accountToId = $matchedPayeeId;
                $payeeToMatched = true;
            }

            if ($accountFromId === null && $transactionType === TransactionType::DEPOSIT->value) {
                $accountFromId = $matchedPayeeId;
                $payeeFromMatched = true;
            }
        }

        return [
            'account_from_id' => $accountFromId,
            'account_to_id' => $accountToId,
            'payee_from_matched' => $payeeFromMatched,
            'payee_to_matched' => $payeeToMatched,
        ];
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

        if ($draftAmount !== null && abs($draftAmount - $transactionAmount) < 0.005) {
            $signals[] = 'amount';
        }

        if (! $transaction->isStandard()) {
            return array_values(array_unique($signals));
        }

        $resolved = $this->resolveDraftAccountIds($draft);

        $transactionFromId = data_get($transaction->config, 'account_from_id');
        if ($resolved['account_from_id'] !== null && is_int($transactionFromId) && $transactionFromId === $resolved['account_from_id']) {
            $signals[] = $resolved['payee_from_matched'] ? 'payee' : 'account_from';
        }

        $transactionToId = data_get($transaction->config, 'account_to_id');
        if ($resolved['account_to_id'] !== null && is_int($transactionToId) && $transactionToId === $resolved['account_to_id']) {
            $signals[] = $resolved['payee_to_matched'] ? 'payee' : 'account_to';
        }

        return array_values(array_unique($signals));
    }
}
