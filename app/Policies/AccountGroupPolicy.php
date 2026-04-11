<?php

namespace App\Policies;

use App\Models\AccountGroup;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Response-based policy results are intentionally not used yet.
 */
class AccountGroupPolicy
{
    use HandlesAuthorization;

    public function isOwnItem(User $user, AccountGroup $accountGroup): bool
    {
        return $user->id === $accountGroup->user_id;
    }

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, AccountGroup $accountGroup): bool
    {
        return $this->isOwnItem($user, $accountGroup);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, AccountGroup $accountGroup): bool
    {
        return $this->isOwnItem($user, $accountGroup);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, AccountGroup $accountGroup): bool
    {
        return $this->isOwnItem($user, $accountGroup);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, AccountGroup $accountGroup): bool
    {
        return $this->isOwnItem($user, $accountGroup);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, AccountGroup $accountGroup): bool
    {
        return $this->isOwnItem($user, $accountGroup);
    }
}
