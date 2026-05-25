<?php

namespace App\Services;

use App\Models\CategoryLearning;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class CategoryLearningManagementService
{
    public function __construct(private CategoryLearningService $categoryLearningService)
    {

    }

    public function getList(User $user, array $filters): Collection
    {
        $query = $user->categoryLearning()
            ->with('category')
            ->when(($filters['search'] ?? null), function ($query, string $search): void {
                $query->where(function ($searchQuery) use ($search): void {
                    $searchQuery->where('item_description', 'like', '%' . $search . '%')
                        ->orWhereHas('category', function ($categoryQuery) use ($search): void {
                            $categoryQuery->where('name', 'like', '%' . $search . '%');
                        });
                });
            })
            ->when(($filters['status'] ?? 'active') === 'active', fn ($query) => $query->where('active', true))
            ->when(($filters['status'] ?? 'active') === 'inactive', fn ($query) => $query->where('active', false));

        return $query->get();
    }

    /**
     * @return array{learning: CategoryLearning, created: bool}
     */
    public function store(User $user, array $data): array
    {
        $normalizedDescription = $this->categoryLearningService->normalize((string) $data['item_description']);

        $learning = $user->categoryLearning()
            ->where('item_description', $normalizedDescription)
            ->first();

        if ($learning === null) {
            return [
                'learning' => $user->categoryLearning()->create([
                    'item_description' => $normalizedDescription,
                    'category_id' => (int) $data['category_id'],
                    'usage_count' => 0,
                    'active' => (bool) $data['active'],
                ]),
                'created' => true,
            ];
        }

        $learning->category_id = (int) $data['category_id'];
        $learning->active = (bool) $data['active'];
        $learning->save();

        return [
            'learning' => $learning,
            'created' => false,
        ];
    }

    public function update(CategoryLearning $categoryLearning, array $data): CategoryLearning
    {
        $normalizedDescription = $this->categoryLearningService->normalize((string) $data['item_description']);

        $categoryLearning->item_description = $normalizedDescription;
        $categoryLearning->category_id = (int) $data['category_id'];
        $categoryLearning->active = (bool) $data['active'];
        $categoryLearning->save();

        return $categoryLearning;
    }

    public function deactivate(CategoryLearning $categoryLearning): CategoryLearning
    {
        if ($categoryLearning->active) {
            $categoryLearning->active = false;
            $categoryLearning->save();
        }

        return $categoryLearning;
    }

    public function activate(CategoryLearning $categoryLearning): CategoryLearning
    {
        if (! $categoryLearning->active) {
            $categoryLearning->active = true;
            $categoryLearning->save();
        }

        return $categoryLearning;
    }

    public function destroy(CategoryLearning $categoryLearning): void
    {
        $categoryLearning->delete();
    }

    public function merge(CategoryLearning $source, CategoryLearning $target): CategoryLearning
    {
        if ($source->category_id !== $target->category_id) {
            throw ValidationException::withMessages([
                'target_id' => __('You can only merge category learning entries that belong to the same category.'),
            ]);
        }

        $target->usage_count += $source->usage_count;
        $target->active = $source->active || $target->active;
        $target->save();

        $source->delete();

        return $target->fresh(['category']);
    }
}
