<?php

namespace App\Services;

use App\Models\Transaction;
use App\Models\TransactionDetailInvestment;
use App\Models\TransactionDetailStandard;
use App\Models\User;

class DuplicateDetectionService
{
    private const int DEFAULT_DATE_WINDOW_DAYS = 3;

    private const float DEFAULT_AMOUNT_TOLERANCE_PERCENT = 10.0;

    private const float DEFAULT_SIMILARITY_THRESHOLD = 0.5;

    private ?AiUserSettingsResolver $settingsResolver = null;

    public function __construct(?AiUserSettingsResolver $settingsResolver = null)
    {
        $this->settingsResolver = $settingsResolver;
    }

    /**
     * Find potential duplicate transactions based on extracted transaction data
     *
     * @param  array{date: string, amount?: float, transaction_type?: string, config_type?: string, account_from_id?: int, account_to_id?: int, investment_id?: int, account_id?: int, payee_id?: int}  $extractedData
     * @return array<int, array{id: int, similarity: float}>
     */
    public function findDuplicates(User $user, array $extractedData): array
    {
        $resolvedSettings = $this->resolveSettings($user);
        $dateWindowDays = max(1, (int) ($resolvedSettings['duplicate_date_window_days'] ?? self::DEFAULT_DATE_WINDOW_DAYS));
        $amountTolerancePercent = (float) ($resolvedSettings['duplicate_amount_tolerance_percent'] ?? self::DEFAULT_AMOUNT_TOLERANCE_PERCENT);
        $similarityThreshold = (float) ($resolvedSettings['duplicate_similarity_threshold'] ?? self::DEFAULT_SIMILARITY_THRESHOLD);

        try {
            $date = \Carbon\Carbon::parse($extractedData['date']);
        } catch (\Carbon\Exceptions\InvalidFormatException) {
            return [];
        }

        $startDate = $date->clone()->subDays($dateWindowDays);
        $endDate = $date->clone()->addDays($dateWindowDays);

        $query = $user->transactions()
            ->whereBetween('date', [$startDate, $endDate]);

        if (isset($extractedData['config_type'])) {
            $query->where('config_type', $extractedData['config_type']);
        }

        $potentialMatches = $query->with(['config', 'transactionItems'])->get();

        return $this->scoreTransactions($extractedData, $potentialMatches, $amountTolerancePercent, $similarityThreshold);
    }

    /**
     * Find potential duplicates by scoring against a pre-loaded in-memory transaction window.
     * Use this instead of findDuplicates() when processing many drafts in a single request
     * to avoid O(n) database round-trips.
     *
     * @param  array{date: string, amount?: float, config_type?: string, account_from_id?: int, account_to_id?: int}  $extractedData
     * @param  \Illuminate\Database\Eloquent\Collection<int, Transaction>  $windowTransactions
     * @return array<int, array{id: int, similarity: float}>
     */
    public function findDuplicatesFromWindow(
        array $extractedData,
        \Illuminate\Database\Eloquent\Collection $windowTransactions,
        float $amountTolerancePercent,
        float $similarityThreshold,
        int $dateWindowDays,
    ): array {
        try {
            $date = \Carbon\Carbon::parse($extractedData['date']);
        } catch (\Carbon\Exceptions\InvalidFormatException) {
            return [];
        }

        $startDate = $date->clone()->subDays($dateWindowDays);
        $endDate = $date->clone()->addDays($dateWindowDays);
        $configType = $extractedData['config_type'] ?? null;

        $candidates = $windowTransactions->filter(
            function (Transaction $transaction) use ($startDate, $endDate, $configType): bool {
                $txDate = $transaction->date;

                if (! ($txDate instanceof \Carbon\Carbon)) {
                    return false;
                }

                if ($txDate->lt($startDate) || $txDate->gt($endDate)) {
                    return false;
                }

                return ! ($configType !== null && $transaction->config_type !== $configType)



                ;
            }
        );

        return $this->scoreTransactions($extractedData, $candidates, $amountTolerancePercent, $similarityThreshold);
    }

    /**
     * @return array<string, mixed>
     */
    public function resolveSettingsForUser(User $user): array
    {
        return $this->resolveSettings($user);
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
     * Score an iterable of transactions against extracted data and return matches above threshold.
     *
     * @param  iterable<Transaction>  $transactions
     * @return array<int, array{id: int, similarity: float}>
     */
    private function scoreTransactions(array $extractedData, iterable $transactions, float $amountTolerancePercent, float $similarityThreshold): array
    {
        $matches = [];

        foreach ($transactions as $transaction) {
            if (! $transaction instanceof Transaction) {
                continue;
            }

            $similarity = $this->calculateSimilarity($extractedData, $transaction, $amountTolerancePercent);

            if ($similarity > $similarityThreshold) {
                $matches[] = [
                    'id' => $transaction->id,
                    'similarity' => round($similarity, 3),
                ];
            }
        }

        usort($matches, fn ($a, $b) => $b['similarity'] <=> $a['similarity']);

        return $matches;
    }

    /**
     * Get transaction total amount using the already-loaded transactionItems relation.
     */
    private function getTransactionAmount(Transaction $transaction): float
    {
        if ($transaction->isStandard()) {
            return (float) $transaction->transactionItems->sum('amount');
        }

        return 0;
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveSettings(User $user): array
    {
        $resolver = $this->settingsResolver ?? app(AiUserSettingsResolver::class);

        return $resolver->resolveForUser($user);
    }
}
