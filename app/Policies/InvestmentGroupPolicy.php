<?php

namespace App\Policies;

use App\Models\InvestmentGroup;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Response-based policy results are intentionally not used yet.
 */
class InvestmentGroupPolicy
{
    use HandlesAuthorization;

    public function isOwnItem(User $user, InvestmentGroup $investmentGroup): bool
    {
        return $user->id === $investmentGroup->user_id;
    }

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, InvestmentGroup $investmentGroup): bool
    {
        return $this->isOwnItem($user, $investmentGroup);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, InvestmentGroup $investmentGroup): bool
    {
        return $this->isOwnItem($user, $investmentGroup);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, InvestmentGroup $investmentGroup): bool
    {
        return $this->isOwnItem($user, $investmentGroup);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, InvestmentGroup $investmentGroup): bool
    {
        return $this->isOwnItem($user, $investmentGroup);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, InvestmentGroup $investmentGroup): bool
    {
        return $this->isOwnItem($user, $investmentGroup);
    }
}
