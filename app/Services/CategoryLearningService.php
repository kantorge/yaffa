<?php

namespace App\Services;

use App\Models\CategoryLearning;
use App\Models\User;

class CategoryLearningService
{
    public function __construct(private User $user)
    {
        // Nothing to initialize
    }

    /**
     * Normalize item description for storage and matching
     */
    public function normalize(string $description): string
    {
        // Lowercase, trim, and remove extra spaces
        $normalized = mb_strtolower(trim($description));

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
     * Get learning data for AI prompt context
     *
     * @return array<array{description: string, category_id: int, usage_count: int}>
     */
    public function getLearningData(): array
    {
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
     * Increment usage count for category learning records
     */
    public function incrementUsageCount(int $categoryLearningId): void
    {
        CategoryLearning::query()
            ->where('id', $categoryLearningId)
            ->where('user_id', $this->user->id)
            ->increment('usage_count');
    }
}
