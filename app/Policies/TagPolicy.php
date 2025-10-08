<?php

namespace App\Policies;

use App\Models\Tag;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class TagPolicy
{
    use HandlesAuthorization;

    public function isOwnItem(User $user, Tag $tag)
    {
        return $user->id === $tag->user_id;
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
     * @param  Tag  $tag
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function view(User $user, Tag $tag): bool
    {
        return $this->isOwnItem($user, $tag);
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
     * @param  Tag  $tag
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function update(User $user, Tag $tag): bool
    {
        return $this->isOwnItem($user, $tag);
    }

    /**
     * Determine whether the user can delete the model.
     *
     * @param  User  $user
     * @param  Tag  $tag
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function delete(User $user, Tag $tag): bool
    {
        return $this->isOwnItem($user, $tag);
    }

    /**
     * Determine whether the user can restore the model.
     *
     * @param  User  $user
     * @param  Tag  $tag
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function restore(User $user, Tag $tag): bool
    {
        return $this->isOwnItem($user, $tag);
    }

    /**
     * Determine whether the user can permanently delete the model.
     *
     * @param  User  $user
     * @param  Tag  $tag
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function forceDelete(User $user, Tag $tag): bool
    {
        return $this->isOwnItem($user, $tag);
    }
}
