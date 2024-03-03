<?php

namespace App\Policies;

use App\Models\Currency;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class CurrencyPolicy
{
    use HandlesAuthorization;

    public function isOwnItem(User $user, Currency $currency)
    {
        return $user->id === $currency->user_id;
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
     * @param  Currency  $currency
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function view(User $user, Currency $currency)
    {
        return $this->isOwnItem($user, $currency);
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
     * @param  Currency  $currency
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function update(User $user, Currency $currency)
    {
        return $this->isOwnItem($user, $currency);
    }

    /**
     * Determine whether the user can delete the model.
     *
     * @param  User  $user
     * @param  Currency  $currency
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function delete(User $user, Currency $currency)
    {
        return $this->isOwnItem($user, $currency);
    }

    /**
     * Determine whether the user can restore the model.
     *
     * @param  User  $user
     * @param  Currency  $currency
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function restore(User $user, Currency $currency)
    {
        return $this->isOwnItem($user, $currency);
    }

    /**
     * Determine whether the user can permanently delete the model.
     *
     * @param  User  $user
     * @param  Currency  $currency
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function forceDelete(User $user, Currency $currency)
    {
        return $this->isOwnItem($user, $currency);
    }
}
