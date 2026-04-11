<?php

namespace App\Services;

use App\Models\CategoryLearning;
use App\Models\User;

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
     * Record a category selection for learning
     *
     * @param string $description Item description to learn from
     * @param int $categoryId Category ID selected by user
     */
    public function recordCategorySelection(string $description, int $categoryId): void
    {
        if (! $this->user) {
            return;
        }

        $normalizedDescription = $this->normalize($description);

        $learning = CategoryLearning::query()
            ->where('user_id', $this->user->id)
            ->where('item_description', $normalizedDescription)
            ->first();

        if ($learning) {
            // If category matches existing learning, increment usage count
            if ((int) $learning->category_id === $categoryId) {
                $learning->increment('usage_count');
            } else {
                // Category changed, reset to new category with count of 1
                $learning->category_id = $categoryId;
                $learning->usage_count = 1;
                $learning->save();
            }
        } else {
            // Create new learning record
            CategoryLearning::create([
                'user_id' => $this->user->id,
                'item_description' => $normalizedDescription,
                'category_id' => $categoryId,
                'usage_count' => 1,
            ]);
        }
    }

}
