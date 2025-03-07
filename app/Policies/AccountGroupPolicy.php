<?php

namespace App\Policies;

use App\Models\AccountGroup;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class AccountGroupPolicy
{
    use HandlesAuthorization;

    public function isOwnItem(User $user, AccountGroup $accountGroup)
    {
        return $user->id === $accountGroup->user_id;
    }

    /**
     * Determine whether the user can view any models.
     *
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function viewAny()
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     *
     * @param  User  $user
     * @param  AccountGroup  $accountGroup
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function view(User $user, AccountGroup $accountGroup)
    {
        return $this->isOwnItem($user, $accountGroup);
    }

    /**
     * Determine whether the user can create models.
     *
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function create()
    {
        return true;
    }

    /**
     * Determine whether the user can update the model.
     *
     * @param  User  $user
     * @param  AccountGroup  $accountGroup
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function update(User $user, AccountGroup $accountGroup)
    {
        return $this->isOwnItem($user, $accountGroup);
    }

    /**
     * Determine whether the user can delete the model.
     *
     * @param  User  $user
     * @param  AccountGroup  $accountGroup
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function delete(User $user, AccountGroup $accountGroup)
    {
        return $this->isOwnItem($user, $accountGroup);
    }

    /**
     * Determine whether the user can restore the model.
     *
     * @param  User  $user
     * @param  AccountGroup  $accountGroup
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function restore(User $user, AccountGroup $accountGroup)
    {
        return $this->isOwnItem($user, $accountGroup);
    }

    /**
     * Determine whether the user can permanently delete the model.
     *
     * @param  User  $user
     * @param  AccountGroup  $accountGroup
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function forceDelete(User $user, AccountGroup $accountGroup)
    {
        return $this->isOwnItem($user, $accountGroup);
    }
}
