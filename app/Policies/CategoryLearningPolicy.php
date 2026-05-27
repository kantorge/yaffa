<?php

namespace App\Policies;

use App\Models\CategoryLearning;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class CategoryLearningPolicy
{
    use HandlesAuthorization;

    private function isOwnItem(User $user, CategoryLearning $categoryLearning): bool
    {
        return $user->id === $categoryLearning->user_id;
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, CategoryLearning $categoryLearning): bool
    {
        return $this->isOwnItem($user, $categoryLearning);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, CategoryLearning $categoryLearning): bool
    {
        return $this->isOwnItem($user, $categoryLearning);
    }

    public function delete(User $user, CategoryLearning $categoryLearning): bool
    {
        return $this->isOwnItem($user, $categoryLearning);
    }
}
