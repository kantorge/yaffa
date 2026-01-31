<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Investment;
use App\Models\Payee;
use App\Models\User;
use Closure;

class AssetMatchingService
{
    public const SIMILARITY_THRESHOLD = 0.5;

    public const MAX_SUGGESTIONS = 10;

    private ?User $user = null;

    public function __construct(?User $user = null)
    {
        $this->user = $user;
    }

    /**
     * Find matching accounts based on similarity
     *
     * @return array<int, array{id: int, name: string, similarity: float}>
     */
    public function matchAccounts(?string $accountName): array
    {
        if (! $accountName || ! $this->user) {
            return [];
        }

        $accounts = $this->user->accounts()->get();

        return $this->calculateMatches($accountName, $accounts, fn (Account $account) => $account->name . ' ' . $account->import_alias);
    }

    /**
     * Format accounts for AI prompt (ID: Name|Aliases)
     */
    public function formatAccountsForPrompt(User $user): string
    {
        $accounts = $user->accounts()
            ->select('id', 'name', 'import_alias')
            ->get();

        if ($accounts->isEmpty()) {
            return 'No accounts configured.';
        }

        $formatted = $accounts->map(function ($account) {
            $aliases = trim($account->import_alias ?? '');
            if ($aliases) {
                return "{$account->id}: {$account->name}|{$aliases}";
            }

            return "{$account->id}: {$account->name}";
        })->join("\n");

        return $formatted;
    }

    /**
     * Find matching payees based on similarity
     *
     * @return array<int, array{id: int, name: string, similarity: float}>
     */
    public function matchPayees(?string $payeeName): array
    {
        if (! $payeeName || ! $this->user) {
            return [];
        }

        $payees = $this->user->payees()->get();

        return $this->calculateMatches($payeeName, $payees, fn (Payee $payee) => $payee->name . ' ' . $payee->import_alias);
    }

    /**
     * Format payees for AI prompt (ID: Name)
     */
    public function formatPayeesForPrompt(User $user): string
    {
        $payees = $user->payees()
            ->select('id', 'name')
            ->get();

        if ($payees->isEmpty()) {
            return 'No payees configured.';
        }

        return $payees->map(fn ($payee) => "{$payee->id}: {$payee->name}")->join("\n");
    }

    /**
     * Find matching investments based on similarity
     *
     * @return array<int, array{id: int, name: string, similarity: float}>
     */
    public function matchInvestments(?string $investmentName): array
    {
        if (! $investmentName || ! $this->user) {
            return [];
        }

        $investments = $this->user->investments()->get();

        return $this->calculateMatches($investmentName, $investments, fn (Investment $investment) => $investment->name . ' ' . $investment->code . ' ' . $investment->isin);
    }

    /**
     * Format investments for AI prompt (ID: Name|Code|ISIN)
     */
    public function formatInvestmentsForPrompt(User $user): string
    {
        $investments = $user->investments()
            ->select('id', 'name', 'code', 'isin')
            ->get();

        if ($investments->isEmpty()) {
            return 'No investments configured.';
        }

        $formatted = $investments->map(function ($investment) {
            $parts = [$investment->name];
            if ($investment->code) {
                $parts[] = $investment->code;
            }
            if ($investment->isin) {
                $parts[] = $investment->isin;
            }

            return "{$investment->id}: " . implode('|', $parts);
        })->join("\n");

        return $formatted;
    }

    /**
     * Calculate similarity matches for a collection of items
     *
     * @template T
     *
     * @param  T[]  $items
     * @param  Closure(T): string  $textExtractor
     * @return array<int, array{id: int, name: string, similarity: float}>
     */
    private function calculateMatches(string $searchText, array $items, Closure $textExtractor): array
    {
        $matches = [];

        $normalizedSearch = $this->normalize($searchText);

        foreach ($items as $item) {
            $itemText = $textExtractor($item);
            $normalizedItem = $this->normalize($itemText);

            $similarity = 0;
            similar_text($normalizedSearch, $normalizedItem, $similarity);
            $similarity /= 100;

            if ($similarity >= config('ai-documents.asset_matching.similarity_threshold', self::SIMILARITY_THRESHOLD)) {
                $matches[] = [
                    'id' => $item->id,
                    'name' => $item->name,
                    'similarity' => round($similarity, 3),
                ];
            }
        }

        // Sort by similarity descending
        usort($matches, fn ($a, $b) => $b['similarity'] <=> $a['similarity']);

        // Return top matches
        $maxSuggestions = config('ai-documents.asset_matching.max_suggestions', self::MAX_SUGGESTIONS);

        return array_slice($matches, 0, $maxSuggestions);
    }

    /**
     * Normalize text for comparison
     */
    private function normalize(string $text): string
    {
        return mb_strtolower(trim($text));
    }
}
