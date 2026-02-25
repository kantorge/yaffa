<?php

namespace App\Services;

use App\Models\Transaction;
use App\Models\TransactionDetailInvestment;
use App\Models\TransactionDetailStandard;
use App\Models\User;

class DuplicateDetectionService
{
    public function __construct(private User $user)
    {
    }

    /**
     * Find potential duplicate transactions based on extracted transaction data
     *
     * @param  array{date: string, amount?: float, transaction_type?: string, config_type?: string, account_from_id?: int, account_to_id?: int, investment_id?: int, account_id?: int, payee_id?: int}  $extractedData
     * @return array<int, array{id: int, similarity: float}>
     */
    public function findDuplicates(array $extractedData): array
    {
        $dateWindowDays = config('ai-documents.duplicate_detection.date_window_days', 3);
        $amountTolerancePercent = config('ai-documents.duplicate_detection.amount_tolerance_percent', 10);

        $date = \Carbon\Carbon::parse($extractedData['date']);
        $startDate = $date->clone()->subDays($dateWindowDays);
        $endDate = $date->clone()->addDays($dateWindowDays);

        // Base query
        $query = $this->user->transactions()
            ->whereBetween('date', [$startDate, $endDate]);

        // Filter by transaction type if provided
        if (isset($extractedData['config_type'])) {
            $query->where('config_type', $extractedData['config_type']);
        }

        $potentialMatches = $query->get();

        $matches = [];

        foreach ($potentialMatches as $transaction) {
            if (! $transaction instanceof Transaction) {
                continue;
            }

            $similarity = $this->calculateSimilarity($extractedData, $transaction, $amountTolerancePercent);

            if ($similarity > config('ai-documents.duplicate_detection.similarity_threshold', 0.5)) {
                $matches[] = [
                    'id' => $transaction->id,
                    'similarity' => round($similarity, 3),
                ];
            }
        }

        // Sort by similarity descending
        usort($matches, fn ($a, $b) => $b['similarity'] <=> $a['similarity']);

        return $matches;
    }

    /**
     * Calculate similarity score between extracted data and an existing transaction
     */
    private function calculateSimilarity(array $extractedData, Transaction $transaction, float $amountTolerancePercent): float
    {
        $score = 0;
        $maxScore = 0;

        // Date match (within window = 1 point)
        $maxScore += 1;
        if (isset($extractedData['date'])) {
            $score += 1;
        }

        // Amount match (within tolerance = 1 point)
        if (isset($extractedData['amount'])) {
            $maxScore += 1;
            $extractedAmount = abs((float) $extractedData['amount']);

            // Get transaction amount
            $transactionAmount = $this->getTransactionAmount($transaction);

            if ($transactionAmount > 0) {
                $tolerance = $transactionAmount * ($amountTolerancePercent / 100);
                if (abs($extractedAmount - $transactionAmount) <= $tolerance) {
                    $score += 1;
                }
            }
        }

        // Account/payee/investment match (up to 2 points)
        $maxScore += 2;
        $assetMatches = $this->countAssetMatches($extractedData, $transaction);
        $score += min($assetMatches, 2);

        return $score / $maxScore;
    }

    /**
     * Count how many assets match between extracted data and transaction
     */
    private function countAssetMatches(array $extractedData, Transaction $transaction): int
    {
        $matches = 0;

        if ($transaction->isStandard()) {
            $config = $transaction->config;

            if (! $config instanceof TransactionDetailStandard) {
                return $matches;
            }

            // Check account_from
            if (isset($extractedData['account_from_id']) && $config->account_from_id === $extractedData['account_from_id']) {
                $matches++;
            }

            // Check account_to
            if (isset($extractedData['account_to_id']) && $config->account_to_id === $extractedData['account_to_id']) {
                $matches++;
            }

            // Check payee
            if (isset($extractedData['payee_id'])) {
                $hasPayeeMatch = $transaction->transactionItems()
                    ->where('payee_id', $extractedData['payee_id'])
                    ->exists();

                if ($hasPayeeMatch) {
                    $matches++;
                }
            }
        } elseif ($transaction->isInvestment()) {
            $config = $transaction->config;

            if (! $config instanceof TransactionDetailInvestment) {
                return $matches;
            }

            // Check investment
            if (isset($extractedData['investment_id']) && $config->investment_id === $extractedData['investment_id']) {
                $matches++;
            }

            // Check account
            if (isset($extractedData['account_id']) && $config->account_id === $extractedData['account_id']) {
                $matches++;
            }
        }

        return $matches;
    }

    /**
     * Get transaction total amount
     */
    private function getTransactionAmount(Transaction $transaction): float
    {
        if ($transaction->isStandard()) {
            return (float) $transaction->transactionItems()
                ->sum('amount');
        }

        return 0;
    }
}
