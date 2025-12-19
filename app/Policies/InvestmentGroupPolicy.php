<?php

namespace App\Policies;

use App\Models\InvestmentGroup;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class InvestmentGroupPolicy
{
    use HandlesAuthorization;

    public function isOwnItem(User $user, InvestmentGroup $investmentGroup)
    {
        return $user->id === $investmentGroup->user_id;
    }

    /**
     * Determine whether the user can view any models.
     *
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     *
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function view(User $user, InvestmentGroup $investmentGroup): bool
    {
        return $this->isOwnItem($user, $investmentGroup);
    }

    /**
     * Determine whether the user can create models.
     *
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the model.
     *
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function update(User $user, InvestmentGroup $investmentGroup): bool
    {
        return $this->isOwnItem($user, $investmentGroup);
    }

    /**
     * Determine whether the user can delete the model.
     *
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function delete(User $user, InvestmentGroup $investmentGroup): bool
    {
        return $this->isOwnItem($user, $investmentGroup);
    }

    /**
     * Determine whether the user can restore the model.
     *
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function restore(User $user, InvestmentGroup $investmentGroup): bool
    {
        return $this->isOwnItem($user, $investmentGroup);
    }

    /**
     * Determine whether the user can permanently delete the model.
     *
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function forceDelete(User $user, InvestmentGroup $investmentGroup): bool
    {
        return $this->isOwnItem($user, $investmentGroup);
    }
}
