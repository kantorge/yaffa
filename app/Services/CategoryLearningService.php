<?php

namespace App\Services;

use App\Models\CategoryLearning;
use App\Models\User;
use Exception;

class CategoryLearningService
{
    private ?User $user = null;

    public function __construct(?User $user = null)
    {
        $this->user = $user;
    }

    /**
     * Normalize item description for storage and matching
     */
    public function normalize(string $description): string
    {
        // Lowercase, trim, and remove extra spaces
        $normalized = mb_strtolower(mb_trim($description));

        // Remove punctuation (but keep spaces)
        $normalized = preg_replace('/[^\w\s]/u', '', $normalized);

        // Remove extra spaces
        $normalized = preg_replace('/\s+/', ' ', $normalized);

        return $normalized;
    }

    /**
     * Save or update category learning data
     */
    public function recordLearning(string $itemDescription, int $categoryId): CategoryLearning
    {
        if (! $this->user) {
            throw new Exception('User not set for CategoryLearningService');
        }

        $normalized = $this->normalize($itemDescription);

        $learning = CategoryLearning::query()
            ->where('user_id', $this->user->id)
            ->where('item_description', $normalized)
            ->first();

        if ($learning) {
            $learning->update(['category_id' => $categoryId]);
        } else {
            $learning = CategoryLearning::create([
                'user_id' => $this->user->id,
                'item_description' => $normalized,
                'category_id' => $categoryId,
                'usage_count' => 0,
            ]);
        }

        return $learning;
    }

    /**
     * Save or update learning data and increment usage count
     */
    public function recordLearningAndIncrement(string $itemDescription, int $categoryId, int $incrementBy = 1): CategoryLearning
    {
        if (! $this->user) {
            throw new Exception('User not set for CategoryLearningService');
        }

        $normalized = $this->normalize($itemDescription);

        $learning = CategoryLearning::query()
            ->where('user_id', $this->user->id)
            ->where('item_description', $normalized)
            ->first();

        if ($learning) {
            $learning->category_id = $categoryId;
            $learning->usage_count += $incrementBy;
            $learning->save();
        } else {
            $learning = CategoryLearning::create([
                'user_id' => $this->user->id,
                'item_description' => $normalized,
                'category_id' => $categoryId,
                'usage_count' => $incrementBy,
            ]);
        }

        return $learning;
    }

    /**
     * Get learning data for AI prompt context
     *
     * @return array<array{description: string, category_id: int, usage_count: int}>
     */
    public function getLearningData(): array
    {
        if (! $this->user) {
            return [];
        }

        return $this->user->categoryLearning()
            ->orderByDesc('usage_count')
            ->limit(50)
            ->get(['item_description', 'category_id', 'usage_count'])
            ->map(fn ($learning) => [
                'description' => $learning->item_description,
                'category_id' => $learning->category_id,
                'usage_count' => $learning->usage_count,
            ])
            ->toArray();
    }

    /**
     * Get category learning data formatted for AI prompt
     */
    public function getLearningDataForPrompt(User $user): string
    {
        $learnings = $user->categoryLearning()
            ->select('item_description', 'category_id')
            ->orderByDesc('usage_count')
            ->limit(50)
            ->get();

        if ($learnings->isEmpty()) {
            return 'No category learning data available.';
        }

        return $learnings->map(fn ($learning) => "{$learning->item_description} → Category ID: {$learning->category_id}")->join("\n");
    }

    /**
     * Increment usage count for category learning records
     */
    public function incrementUsageCount(int $categoryLearningId): void
    {
        if (! $this->user) {
            return;
        }

        CategoryLearning::query()
            ->where('id', $categoryLearningId)
            ->where('user_id', $this->user->id)
            ->increment('usage_count');
    }

    /**
     * Increment usage count for a normalized description and category.
     */
    public function incrementUsageCountForDescription(string $itemDescription, int $categoryId): void
    {
        if (! $this->user) {
            return;
        }

        $normalized = $this->normalize($itemDescription);

        CategoryLearning::query()
            ->where('user_id', $this->user->id)
            ->where('item_description', $normalized)
            ->where('category_id', $categoryId)
            ->increment('usage_count');
    }
}
