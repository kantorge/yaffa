<?php

namespace App\Services;

use App\Models\AccountEntity;
use App\Models\Investment;
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

        return $this->calculateMatches(
            $accountName,
            $accounts,
            fn (AccountEntity $account) => $account->name . ($account->import_alias ? ' (' . $account->import_alias . ')' : '')
        );
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
            $aliases = mb_trim($account->import_alias ?? '');
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

        return $this->calculateMatches(
            $payeeName,
            $payees,
            fn (AccountEntity $payee) => $payee->name . ($payee->import_alias ? ' (' . $payee->import_alias . ')' : '')
        );
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

        return $this->calculateMatches(
            $investmentName,
            $investments,
            fn (Investment $investment) => $investment->name . ($investment->symbol ? ' (symbol: ' . $investment->symbol . ')' : '') . ($investment->isin ? ' (ISIN: ' . $investment->isin . ')' : '')
        );
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
     * Find matching category learning records based on similarity
     *
     * @return array<int, array{id: int, description: string, category_id: int, category_name: string, similarity: float}>
     */
    public function matchCategoryLearning(?string $description): array
    {
        if (! $description || ! $this->user) {
            return [];
        }

        $learningRecords = $this->user->categoryLearning()
            ->with('category')
            ->whereHas('category', fn ($q) => $q->where('active', 1))
            ->get();

        $matches = [];
        $normalizedSearch = $this->normalize($description);

        foreach ($learningRecords as $learning) {
            $normalizedItem = $this->normalize($learning->item_description);

            $similarity = 0;
            similar_text($normalizedSearch, $normalizedItem, $similarity);
            $similarity /= 100;

            if ($similarity >= config('ai-documents.asset_matching.similarity_threshold', self::SIMILARITY_THRESHOLD)) {
                $matches[] = [
                    'id' => $learning->id,
                    'description' => $learning->item_description,
                    'category_id' => $learning->category_id,
                    'category_name' => $learning->category->full_name,
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
     * Format category learning records for AI prompt (ID: Description [usage_count uses])
     */
    public function formatCategoryLearningForPrompt(User $user): string
    {
        $learningRecords = $user->categoryLearning()
            ->with('category')
            ->whereHas('category', fn ($q) => $q->where('active', 1))
            ->orderByDesc('usage_count')
            ->limit(50)
            ->get();

        if ($learningRecords->isEmpty()) {
            return 'No category learning data available.';
        }

        return $learningRecords->map(fn ($learning) => "{$learning->category_id}: {$learning->item_description} ({$learning->usage_count} uses)")->join("\n");
    }

    /**
     * Format active categories for AI prompt (ID: Full Name)
     */
    public function formatCategoriesForPrompt(User $user): string
    {
        $categories = $user->categories()
            ->active()
            ->with('parent')
            ->get()
            ->sortBy('full_name');

        if ($categories->isEmpty()) {
            return 'No active categories configured.';
        }

        return $categories->map(fn ($category) => "{$category->id}: {$category->full_name}")->join("\n");
    }

    /**
     * Calculate similarity matches for a collection of items
     *
     * @template T
     *
     * @param  iterable<T>  $items
     * @param  Closure(T): string  $textExtractor
     * @return array<int, array{id: int, name: string, similarity: float}>
     */
    private function calculateMatches(string $searchText, iterable $items, Closure $textExtractor): array
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
        return mb_strtolower(mb_trim($text));
    }
}
