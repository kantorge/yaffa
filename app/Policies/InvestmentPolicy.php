<?php

namespace App\Policies;

use App\Models\Investment;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class InvestmentPolicy
{
    use HandlesAuthorization;

    public function isOwnItem(User $user, Investment $investment)
    {
        return $user->id === $investment->user_id;
    }

    /**
     * Determine whether the user can view any models.
     *
     * @param  User  $user
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     *
     * @param  User  $user
     * @param  Investment  $investment
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function view(User $user, Investment $investment): bool
    {
        return $this->isOwnItem($user, $investment);
    }

    /**
     * Determine whether the user can create models.
     *
     * @param  User  $user
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the model.
     *
     * @param  User  $user
     * @param  Investment  $investment
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function update(User $user, Investment $investment): bool
    {
        return $this->isOwnItem($user, $investment);
    }

    /**
     * Determine whether the user can delete the model.
     *
     * @param  User  $user
     * @param  Investment  $investment
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function delete(User $user, Investment $investment): bool
    {
        return $this->isOwnItem($user, $investment);
    }

    /**
     * Determine whether the user can restore the model.
     *
     * @param  User  $user
     * @param  Investment  $investment
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function restore(User $user, Investment $investment): bool
    {
        return $this->isOwnItem($user, $investment);
    }

    /**
     * Determine whether the user can permanently delete the model.
     *
     * @param  User  $user
     * @param  Investment  $investment
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function forceDelete(User $user, Investment $investment): bool
    {
        return $this->isOwnItem($user, $investment);
    }
}
