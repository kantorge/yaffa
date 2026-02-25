<?php

namespace App\Services;

use App\Models\User;
use App\Models\Category;
use App\Models\CategoryLearning;
use App\Models\Investment;
use Illuminate\Support\Str;

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
    public function matchAccounts(string $accountName): array
    {
        if (! $accountName || ! $this->user) {
            return [];
        }

        $accounts = $this->user
            ->accounts()
            ->select('id', 'name', 'alias')
            ->get();

        /** @var array<int, array{id: int, name: string, similarity: float}> $matches */
        $matches = [];

        foreach ($accounts as $account) {
            // Split alias by newline to handle multiple alias values
            $secondaryParts = $account->alias ? array_filter(array_map('trim', explode("\n", $account->alias))) : [];

            $similarity = $this->calculatePartialSimilarity(
                $accountName,
                $account->name,
                $secondaryParts ?: null
            );

            if ($similarity >= config('ai-documents.asset_matching.similarity_threshold', self::SIMILARITY_THRESHOLD)) {
                $matches[] = [
                    'id' => $account->id,
                    'name' => $account->name . ($account->alias ? ' (' . $account->alias . ')' : ''),
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
     * Find matching payees based on similarity
     *
     * @return array<int, array{id: int, name: string, similarity: float}>
     */
    public function matchPayees(string $payeeName): array
    {
        if (! $payeeName || ! $this->user) {
            return [];
        }

        $payees = $this->user
            ->payees()
            ->select('id', 'name', 'alias')
            ->get();

        /** @var array<int, array{id: int, name: string, similarity: float}> $matches */
        $matches = [];

        foreach ($payees as $payee) {
            // Split alias by newline to handle multiple alias values
            $secondaryParts = $payee->alias ? array_filter(array_map('trim', explode("\n", $payee->alias))) : [];

            $similarity = $this->calculatePartialSimilarity(
                $payeeName,
                $payee->name,
                $secondaryParts ?: null
            );

            if ($similarity >= config('ai-documents.asset_matching.similarity_threshold', self::SIMILARITY_THRESHOLD)) {
                $matches[] = [
                    'id' => $payee->id,
                    'name' => $payee->name . ($payee->alias ? ' (' . $payee->alias . ')' : ''),
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
     * Find matching investments based on similarity
     *
     * @return array<int, array{id: int, name: string, similarity: float}>
     */
    public function matchInvestments(string $investmentName): array
    {
        if (! $investmentName || ! $this->user) {
            return [];
        }

        $investments = $this->user->investments()->get();

        /** @var array<int, array{id: int, name: string, similarity: float}> $matches */
        $matches = [];

        foreach ($investments as $investment) {
            if (! $investment instanceof Investment) {
                continue;
            }

            // Create array with symbol and ISIN as separate secondary parts
            $secondaryParts = array_filter([
                $investment->symbol,
                $investment->isin,
            ]);

            $similarity = $this->calculatePartialSimilarity(
                $investmentName,
                $investment->name,
                $secondaryParts ?: null
            );

            if ($similarity >= config('ai-documents.asset_matching.similarity_threshold', self::SIMILARITY_THRESHOLD)) {
                $matches[] = [
                    'id' => $investment->id,
                    'name' => $investment->name .
                        ($investment->symbol ? ' (symbol: ' . $investment->symbol . ')' : '') .
                        ($investment->isin ? ' (ISIN: ' . $investment->isin . ')' : ''),
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
     * Find matching category learning records based on similarity
     *
     * @return array<int, array{id: int, description: string, category_id: int, category_name: string, similarity: float}>
     */
    public function matchCategoryLearning(string $description): array
    {
        if (! $description || ! $this->user) {
            return [];
        }

        $learningRecords = $this->user->categoryLearning()
            ->with('category')
            ->whereHas('category', fn ($q) => $q->where('active', 1))
            ->get();

        /** @var array<int, array{id: int, description: string, category_id: int, category_name: string, similarity: float}> $matches */
        $matches = [];
        $normalizedSearch = $this->normalize($description);

        foreach ($learningRecords as $learning) {
            if (! $learning instanceof CategoryLearning) {
                continue;
            }

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

        return $learningRecords
            ->filter(fn ($learning): bool => $learning instanceof CategoryLearning)
            ->map(fn (CategoryLearning $learning): string => "{$learning->category_id}: {$learning->item_description} ({$learning->usage_count} uses)")
            ->join("\n");
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

        return $categories
            ->map(fn (Category $category): string => "{$category->id}: {$category->full_name}")
            ->join("\n");
    }

    /**
     * Calculate similarity against primary and optional secondary parts
     *
     * Compares search text against primary part first, then against each secondary part separately.
     * Returns the maximum similarity score. This prevents dilution of scores when
     * full strings include additional metadata.
     *
     * @param  array<string>|null  $secondary  Optional array of secondary strings to compare against
     */
    private function calculatePartialSimilarity(string $searchText, string $primary, ?array $secondary = null): float
    {
        $normalizedSearch = $this->normalize($searchText);

        // Compare against primary part
        $normalizedPrimary = $this->normalize($primary);
        $primarySimilarity = 0;
        similar_text($normalizedSearch, $normalizedPrimary, $primarySimilarity);
        $primarySimilarity /= 100;

        // For extreme verbose logging
        //Log::debug("Comparing '{$normalizedSearch}' with '{$normalizedPrimary}' (primary) => similarity: {$primarySimilarity}");

        // If no secondary parts, return primary score
        if (! $secondary) {
            return $primarySimilarity;
        }

        // Compare against each secondary part and find the maximum
        $maxSecondarySimilarity = 0;
        foreach ($secondary as $secondaryPart) {
            if (! $secondaryPart) {
                continue;
            }

            $normalizedSecondary = $this->normalize($secondaryPart);
            $secondarySimilarity = 0;
            similar_text($normalizedSearch, $normalizedSecondary, $secondarySimilarity);
            $secondarySimilarity /= 100;

            // For extreme verbose logging
            //Log::debug("Comparing '{$normalizedSearch}' with '{$normalizedSecondary}' (secondary) => similarity: {$secondarySimilarity}");

            $maxSecondarySimilarity = max($maxSecondarySimilarity, $secondarySimilarity);
        }

        // Return maximum of primary and best secondary match
        return max($primarySimilarity, $maxSecondarySimilarity);
    }

    /**
     * Normalize text for comparison
     */
    private function normalize(string $text): string
    {
        return Str::lower(Str::trim($text));
    }
}
