<?php

namespace App\Policies;

use App\Models\Currency;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Response-based policy results are intentionally not used yet.
 */
class CurrencyPolicy
{
    use HandlesAuthorization;

    public function isOwnItem(User $user, Currency $currency): bool
    {
        return $user->id === $currency->user_id;
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
    public function view(User $user, Currency $currency): bool
    {
        return $this->isOwnItem($user, $currency);
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
    public function update(User $user, Currency $currency): bool
    {
        return $this->isOwnItem($user, $currency);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Currency $currency): bool
    {
        return $this->isOwnItem($user, $currency);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Currency $currency): bool
    {
        return $this->isOwnItem($user, $currency);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Currency $currency): bool
    {
        return $this->isOwnItem($user, $currency);
    }
}
