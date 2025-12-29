<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

/**
 * NLP Service Client
 * 
 * Communicates with the NLP microservice for payee classification tasks:
 * - Finding duplicate payees using semantic similarity
 * - Identifying transfer transactions
 */
class NlpService
{
    private string $baseUrl;
    private int $timeout;

    public function __construct()
    {
        $this->baseUrl = config('yaffa.nlp_service_url', 'http://nlp-service:5000');
        $this->timeout = config('yaffa.nlp_service_timeout', 30);
    }

    /**
     * Check if the NLP service is available
     */
    public function isAvailable(): bool
    {
        try {
            $response = Http::timeout(5)->get("{$this->baseUrl}/health");
            return $response->successful();
        } catch (\Exception $e) {
            Log::warning('NLP service health check failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Find duplicate payees using semantic similarity
     * 
     * @param array $payees Array of payees with 'id' and 'name' keys
     * @param float $threshold Similarity threshold (0.0-1.0), default 0.85
     * @return array|null Array of duplicate groups or null on error
     */
    public function findDuplicates(array $payees, float $threshold = 0.85): ?array
    {
        if (empty($payees)) {
            return ['duplicate_groups' => [], 'total_duplicates_found' => 0];
        }

        try {
            $response = Http::timeout($this->timeout)
                ->post("{$this->baseUrl}/api/find-duplicates", [
                    'payees' => $payees,
                    'threshold' => $threshold,
                ]);

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('NLP service find-duplicates failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('Exception calling NLP service find-duplicates: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Classify if a transaction is a transfer based on payee name
     * 
     * @param string $payeeName The payee name to classify
     * @param string|null $transactionType Optional: 'withdrawal', 'deposit', etc.
     * @param string|null $description Optional: Additional context
     * @return array|null Classification result or null on error
     */
    public function classifyTransfer(
        string $payeeName,
        ?string $transactionType = null,
        ?string $description = null
    ): ?array {
        try {
            $payload = ['payee_name' => $payeeName];
            
            if ($transactionType) {
                $payload['transaction_type'] = $transactionType;
            }
            
            if ($description) {
                $payload['description'] = $description;
            }

            $response = Http::timeout($this->timeout)
                ->post("{$this->baseUrl}/api/classify/transfer", $payload);

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('NLP service classify-transfer failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('Exception calling NLP service classify-transfer: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Calculate similarity between two payee names
     * 
     * @param string $name1 First payee name
     * @param string $name2 Second payee name
     * @param float $threshold Similarity threshold (0.0-1.0), default 0.85
     * @return array|null Similarity result or null on error
     */
    public function calculateSimilarity(
        string $name1,
        string $name2,
        float $threshold = 0.85
    ): ?array {
        try {
            $response = Http::timeout($this->timeout)
                ->post("{$this->baseUrl}/api/similarity", [
                    'name1' => $name1,
                    'name2' => $name2,
                    'threshold' => $threshold,
                ]);

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('NLP service similarity failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('Exception calling NLP service similarity: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Find and cache duplicate payees for a user
     * 
     * @param int $userId User ID
     * @param float $threshold Similarity threshold
     * @param int $cacheTtl Cache TTL in seconds (default 1 hour)
     * @return array|null
     */
    public function findUserPayeeDuplicates(
        int $userId,
        float $threshold = 0.85,
        int $cacheTtl = 3600
    ): ?array {
        $cacheKey = "nlp_duplicates_user_{$userId}_threshold_" . (int)($threshold * 100);

        return Cache::remember($cacheKey, $cacheTtl, function () use ($userId, $threshold) {
            // Get all payees for this user (exclude accounts and investments)
            $payees = \App\Models\AccountEntity::payees()
                ->where('user_id', $userId)
                ->whereNotNull('name')
                ->where('name', '!=', '')
                ->get(['id', 'name'])
                ->map(fn($p) => ['id' => $p->id, 'name' => $p->name])
                ->toArray();

            if (empty($payees)) {
                return ['duplicate_groups' => [], 'total_duplicates_found' => 0];
            }

            return $this->findDuplicates($payees, $threshold);
        });
    }

    /**
     * Clear cached duplicate results for a user
     * 
     * @param int $userId User ID
     */
    public function clearUserDuplicatesCache(int $userId): void
    {
        // Clear common thresholds
        foreach ([75, 80, 85, 90, 95] as $threshold) {
            Cache::forget("nlp_duplicates_user_{$userId}_threshold_{$threshold}");
        }
    }
}
