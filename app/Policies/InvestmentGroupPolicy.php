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
     * @param  User  $user
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function viewAny(User $user)
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     *
     * @param  User  $user
     * @param  InvestmentGroup  $investmentGroup
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function view(User $user, InvestmentGroup $investmentGroup)
    {
        return $this->isOwnItem($user, $investmentGroup);
    }

    /**
     * Determine whether the user can create models.
     *
     * @param  User  $user
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function create(User $user)
    {
        return true;
    }

    /**
     * Determine whether the user can update the model.
     *
     * @param  User  $user
     * @param  InvestmentGroup  $investmentGroup
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function update(User $user, InvestmentGroup $investmentGroup)
    {
        return $this->isOwnItem($user, $investmentGroup);
    }

    /**
     * Determine whether the user can delete the model.
     *
     * @param  User  $user
     * @param  InvestmentGroup  $investmentGroup
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function delete(User $user, InvestmentGroup $investmentGroup)
    {
        return $this->isOwnItem($user, $investmentGroup);
    }

    /**
     * Determine whether the user can restore the model.
     *
     * @param  User  $user
     * @param  InvestmentGroup  $investmentGroup
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function restore(User $user, InvestmentGroup $investmentGroup)
    {
        return $this->isOwnItem($user, $investmentGroup);
    }

    /**
     * Determine whether the user can permanently delete the model.
     *
     * @param  User  $user
     * @param  InvestmentGroup  $investmentGroup
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function forceDelete(User $user, InvestmentGroup $investmentGroup)
    {
        return $this->isOwnItem($user, $investmentGroup);
    }
}
