<?php

namespace App\Policies;

use App\Models\Currency;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Auth\Access\Response;

class CurrencyPolicy
{
    use HandlesAuthorization;

    public function isOwnItem(User $user, Currency $currency): bool
    {
        return $user->id === $currency->user_id;
    }

    /**
     * Determine whether the user can view any models.
     *
     * @return Response|bool
     */
    public function viewAny(): Response|bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     *
     * @param User $user
     * @param Currency $currency
     * @return Response|bool
     */
    public function view(User $user, Currency $currency): Response|bool
    {
        return $this->isOwnItem($user, $currency);
    }

    /**
     * Determine whether the user can create models.
     *
     * @return Response|bool
     */
    public function create(): Response|bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the model.
     *
     * @param User $user
     * @param Currency $currency
     * @return Response|bool
     */
    public function update(User $user, Currency $currency): Response|bool
    {
        return $this->isOwnItem($user, $currency);
    }

    /**
     * Determine whether the user can delete the model.
     *
     * @param User $user
     * @param Currency $currency
     * @return Response|bool
     */
    public function delete(User $user, Currency $currency): Response|bool
    {
        return $this->isOwnItem($user, $currency);
    }

    /**
     * Determine whether the user can restore the model.
     *
     * @param User $user
     * @param Currency $currency
     * @return Response|bool
     */
    public function restore(User $user, Currency $currency): Response|bool
    {
        return $this->isOwnItem($user, $currency);
    }

    /**
     * Determine whether the user can permanently delete the model.
     *
     * @param User $user
     * @param Currency $currency
     * @return Response|bool
     */
    public function forceDelete(User $user, Currency $currency): Response|bool
    {
        return $this->isOwnItem($user, $currency);
    }
}
