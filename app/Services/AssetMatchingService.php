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

    /**
     * @var array<string, mixed>|null
     */
    private ?array $resolvedSettings = null;

    private ?int $resolvedSettingsUserId = null;

    private ?AiUserSettingsResolver $settingsResolver = null;

    public function __construct(?User $user = null, ?AiUserSettingsResolver $settingsResolver = null)
    {
        $this->user = $user;
        $this->settingsResolver = $settingsResolver;
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

        $similarityThreshold = $this->resolveSimilarityThreshold();

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

            if ($similarity >= $similarityThreshold) {
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
        $maxSuggestions = $this->resolveMaxSuggestions();

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

        $similarityThreshold = $this->resolveSimilarityThreshold();

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

            if ($similarity >= $similarityThreshold) {
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
        $maxSuggestions = $this->resolveMaxSuggestions();

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

        $similarityThreshold = $this->resolveSimilarityThreshold();

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

            if ($similarity >= $similarityThreshold) {
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
        $maxSuggestions = $this->resolveMaxSuggestions();

        return array_slice($matches, 0, $maxSuggestions);
    }

    /**
     * Find matching category learning records based on similarity
     *
     * @return array<int, array{id: int, description: string, category_id: int}>
     */
    public function matchCategoryLearning(string $description): array
    {
        if (! $description || ! $this->user) {
            return [];
        }

        $similarityThreshold = $this->resolveSimilarityThreshold();

        $learningRecords = $this->user->categoryLearning()
            ->whereHas('category', fn ($q) => $q->where('active', 1))
            ->get();

        /** @var array<string, array{id: int, description: string, category_id: int, similarity: float, usage_count: int}> $bestMatchesByCategory */
        $bestMatchesByCategory = [];
        $normalizedSearch = $this->normalize($description);

        foreach ($learningRecords as $learning) {
            if (! $learning instanceof CategoryLearning) {
                continue;
            }

            $normalizedItem = $this->normalize($learning->item_description);

            $similarity = 0;
            similar_text($normalizedSearch, $normalizedItem, $similarity);
            $similarity /= 100;

            if ($similarity < $similarityThreshold) {
                continue;
            }

            $categoryKey = (string) $learning->category_id;
            $candidate = [
                'id' => $learning->id,
                'description' => $learning->item_description,
                'category_id' => $learning->category_id,
                'similarity' => $similarity,
                'usage_count' => (int) $learning->usage_count,
            ];

            $existingCandidate = $bestMatchesByCategory[$categoryKey] ?? null;
            if (
                $existingCandidate === null
                || $candidate['similarity'] > $existingCandidate['similarity']
                || ($candidate['similarity'] === $existingCandidate['similarity'] && $candidate['usage_count'] > $existingCandidate['usage_count'])
            ) {
                $bestMatchesByCategory[$categoryKey] = $candidate;
            }
        }

        $matches = array_values($bestMatchesByCategory);

        // Sort by similarity descending
        usort($matches, function (array $a, array $b): int {
            $similarityComparison = $b['similarity'] <=> $a['similarity'];
            if ($similarityComparison !== 0) {
                return $similarityComparison;
            }

            return $b['usage_count'] <=> $a['usage_count'];
        });

        // Return top matches
        $maxSuggestions = $this->resolveMaxSuggestions();
        $selectedMatches = array_slice($matches, 0, $maxSuggestions);

        return array_map(
            fn (array $match): array => [
                'id' => (int) $match['id'],
                'description' => (string) $match['description'],
                'category_id' => (int) $match['category_id'],
            ],
            $selectedMatches,
        );
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
    public function formatCategoriesForPrompt(User $user, string $categoryMatchingMode = 'best_match'): string
    {
        return $this->resolveCategoryPromptContext($user, $categoryMatchingMode)['categories_list'];
    }

    /**
     * @return array{categories_list: string, requested_category_matching_mode: string, applied_category_matching_mode: string, used_mode_fallback: bool}
     */
    public function resolveCategoryPromptContext(User $user, string $categoryMatchingMode = 'best_match'): array
    {
        $requestedCategoryMatchingMode = $this->normalizeCategoryMatchingMode($categoryMatchingMode);

        $categories = $user->categories()
            ->active()
            ->with('parent')
            ->withCount([
                'children as active_children_count' => fn ($query) => $query->where('active', 1),
            ])
            ->get()
            ->sortBy('full_name')
            ->values();

        if ($categories->isEmpty()) {
            return [
                'categories_list' => 'No active categories configured.',
                'requested_category_matching_mode' => $requestedCategoryMatchingMode,
                'applied_category_matching_mode' => $requestedCategoryMatchingMode,
                'used_mode_fallback' => false,
            ];
        }

        $filteredCategories = $this->filterCategoriesForPrompt($categories, $requestedCategoryMatchingMode);
        $appliedCategoryMatchingMode = $requestedCategoryMatchingMode;
        $usedModeFallback = false;

        if ($filteredCategories->isEmpty() && $requestedCategoryMatchingMode !== 'best_match') {
            $filteredCategories = $categories;
            $appliedCategoryMatchingMode = 'best_match';
            $usedModeFallback = true;
        }

        return [
            'categories_list' => $filteredCategories
                ->map(fn (Category $category): string => "{$category->id}: {$category->full_name}")
                ->join("\n"),
            'requested_category_matching_mode' => $requestedCategoryMatchingMode,
            'applied_category_matching_mode' => $appliedCategoryMatchingMode,
            'used_mode_fallback' => $usedModeFallback,
        ];
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
        $primarySimilarity = $this->computeSimilarity($normalizedSearch, $normalizedPrimary);

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
            $secondarySimilarity = $this->computeSimilarity($normalizedSearch, $normalizedSecondary);

            $maxSecondarySimilarity = max($maxSecondarySimilarity, $secondarySimilarity);
        }

        // Return maximum of primary and best secondary match
        return max($primarySimilarity, $maxSecondarySimilarity);
    }

    /**
     * Compute similarity between two already-normalized strings.
     *
     * The standard similar_text percentage is `2 * matching_chars / (len_a + len_b)`, which
     * under-scores when a short available name (e.g. "Amazon") is a prefix of a long identified
     * name from a bank statement (e.g. "amazon marketplace eu sarl").
     *
     * To handle that, when the strings differ in length we also compare the shorter string against
     * the same-length prefix of the longer string and return the maximum of both scores.
     */
    private function computeSimilarity(string $a, string $b): float
    {
        similar_text($a, $b, $standardPercent);
        $standardSimilarity = $standardPercent / 100;

        $lenA = mb_strlen($a);
        $lenB = mb_strlen($b);

        // No length difference — standard score is sufficient
        if ($lenA === $lenB) {
            return $standardSimilarity;
        }

        // Compare the shorter string against the matching-length prefix of the longer string
        [$shorter, $longer] = $lenA < $lenB ? [$a, $b] : [$b, $a];
        $longerPrefix = mb_substr($longer, 0, mb_strlen($shorter));

        similar_text($shorter, $longerPrefix, $prefixPercent);
        $prefixSimilarity = $prefixPercent / 100;

        return max($standardSimilarity, $prefixSimilarity);
    }

    /**
     * Normalize text for comparison
     */
    private function normalize(string $text): string
    {
        return Str::lower(Str::trim($text));
    }

    private function normalizeCategoryMatchingMode(string $categoryMatchingMode): string
    {
        if (! in_array($categoryMatchingMode, AiUserSettingsResolver::CATEGORY_MATCHING_MODES, true)) {
            return 'best_match';
        }

        return $categoryMatchingMode;
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Collection<int, Category>  $categories
     * @return \Illuminate\Database\Eloquent\Collection<int, Category>
     */
    private function filterCategoriesForPrompt(\Illuminate\Database\Eloquent\Collection $categories, string $categoryMatchingMode): \Illuminate\Database\Eloquent\Collection
    {
        return match ($categoryMatchingMode) {
            'parent_only' => $categories
                ->filter(fn (Category $category): bool => $category->parent_id === null)
                ->values(),
            'child_only' => $categories
                ->filter(fn (Category $category): bool => $category->parent_id !== null)
                ->values(),
            // 'best_match' mode is handled in the calling method as a fallback, so it doesn't require filtering here
            // This includes 'parent_preferred' and 'child_preferred' modes, where we want to keep all categories but prefer matches from the specified group in the matching logic
            default => $categories,
        };
    }

    private function resolveSimilarityThreshold(): float
    {
        return (float) $this->resolveUserSetting('asset_similarity_threshold', self::SIMILARITY_THRESHOLD);
    }

    private function resolveMaxSuggestions(): int
    {
        return max(1, (int) $this->resolveUserSetting('asset_max_suggestions', self::MAX_SUGGESTIONS));
    }

    private function resolveUserSetting(string $key, int|float $fallback): int|float
    {
        if (! $this->user) {
            return $fallback;
        }

        if ($this->resolvedSettingsUserId !== $this->user->id || $this->resolvedSettings === null) {
            $resolver = $this->settingsResolver ?? app(AiUserSettingsResolver::class);
            $this->resolvedSettings = $resolver->resolveForUser($this->user);
            $this->resolvedSettingsUserId = $this->user->id;
        }

        $value = $this->resolvedSettings[$key] ?? null;

        if ($value === null) {
            return $fallback;
        }

        return $value;
    }
}
