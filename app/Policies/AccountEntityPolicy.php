<?php

namespace App\Policies;

use App\Models\AccountEntity;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class AccountEntityPolicy
{
    use HandlesAuthorization;

    public function isOwnItem(User $user, AccountEntity $accountEntity)
    {
        return $user->id === $accountEntity->user_id;
    }

    /**
     * Determine whether the user can view any models.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function viewAny(User $user)
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\AccountEntity  $accountEntity
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function view(User $user, AccountEntity $accountEntity)
    {
        return $this->isOwnItem($user, $accountEntity);
    }

    /**
     * Determine whether the user can create models.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function create(User $user)
    {
        return true;
    }

    /**
     * Determine whether the user can update the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\AccountEntity  $accountEntity
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function update(User $user, AccountEntity $accountEntity)
    {
        return $this->isOwnItem($user, $accountEntity);
    }

    /**
     * Determine whether the user can delete the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\AccountEntity  $accountEntity
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function delete(User $user, AccountEntity $accountEntity)
    {
        return $this->isOwnItem($user, $accountEntity);
    }

    /**
     * Determine whether the user can restore the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\AccountEntity  $accountEntity
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function restore(User $user, AccountEntity $accountEntity)
    {
        return $this->isOwnItem($user, $accountEntity);
    }

    /**
     * Determine whether the user can permanently delete the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\AccountEntity  $accountEntity
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function forceDelete(User $user, AccountEntity $accountEntity)
    {
        return $this->isOwnItem($user, $accountEntity);
    }
}
