<?php

namespace App\Services\Import;

use App\Enums\TransactionType;
use App\Models\Transaction;
use App\Models\User;
use App\Services\DuplicateDetectionService;

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
        $enriched = [];

        foreach ($drafts as $draft) {
            $input = $this->toDuplicateDetectionInput($draft);

            if ($input === null) {
                $draft['duplicate_candidates'] = [];
                $enriched[] = $draft;
                continue;
            }

            $matches = $this->duplicateDetectionService->findDuplicates($user, $input);
            $candidateIds = collect($matches)
                ->pluck('id')
                ->filter(fn (mixed $id) => is_int($id))
                ->take(10)
                ->values()
                ->all();

            $transactions = Transaction::query()
                ->whereIn('id', $candidateIds)
                ->with(['config', 'transactionItems'])
                ->get()
                ->keyBy('id');

            $draft['duplicate_candidates'] = collect($matches)
                ->take(10)
                ->map(function (array $match) use ($draft, $transactions): array {
                    $transactionId = (int) ($match['id'] ?? 0);
                    /** @var Transaction|null $transaction */
                    $transaction = $transactions->get($transactionId);

                    $similarity = (float) ($match['similarity'] ?? 0.0);

                    return [
                        'transaction_id' => $transactionId,
                        'confidence_score' => round($similarity, 3),
                        'similarity_score' => round($similarity, 3),
                        'matched_on' => $this->buildMatchedOnSignals($draft, $transaction),
                        'summary' => [
                            'date' => $transaction?->date?->format('Y-m-d'),
                            'comment' => $transaction?->comment,
                            'amount' => $transaction ? (float) $transaction->transactionItems()->sum('amount') : null,
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
     * @param  array<string, mixed>  $draft
     * @return array<string, mixed>|null
     */
    private function toDuplicateDetectionInput(array $draft): ?array
    {
        if (! is_string($draft['date'] ?? null)) {
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
        $transactionAmount = (float) $transaction->transactionItems()->sum('amount');

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
