<?php

namespace App\Policies;

use App\Models\AccountEntity;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class AccountEntityPolicy
{
    use HandlesAuthorization;

    public function isOwnItem(User $user, AccountEntity $accountEntity): bool
    {
        return $user->id === $accountEntity->user_id;
    }

    /**
     * Determine whether the user can view any models.
     *
     * @return bool
     */
    public function viewAny(): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     *
     * @param User $user
     * @param AccountEntity $accountEntity
     * @return bool
     */
    public function view(User $user, AccountEntity $accountEntity): bool
    {
        return $this->isOwnItem($user, $accountEntity);
    }

    /**
     * Determine whether the user can create models.
     *
     * @return bool
     */
    public function create(): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the model.
     *
     * @param User $user
     * @param AccountEntity $accountEntity
     * @return bool
     */
    public function update(User $user, AccountEntity $accountEntity): bool
    {
        return $this->isOwnItem($user, $accountEntity);
    }

    /**
     * Determine whether the user can permanently delete the model.
     *
     * @param User $user
     * @param AccountEntity $accountEntity
     * @return bool
     */
    public function forceDelete(User $user, AccountEntity $accountEntity): bool
    {
        return $this->isOwnItem($user, $accountEntity);
    }
}
