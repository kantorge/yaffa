<?php

namespace App\Services;

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

}
