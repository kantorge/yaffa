<?php

namespace App\Policies;

use App\Models\ReceivedMail;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Auth\Access\Response;

class ReceivedMailPolicy
{
    use HandlesAuthorization;

    public function isOwnItem(User $user, ReceivedMail $receivedMail)
    {
        return $user->id === $receivedMail->user_id;
    }

    /**
     * Determine whether the user can view any models.
     *
     * @return Response|bool
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     *
     * @return Response|bool
     */
    public function view(User $user, ReceivedMail $receivedMail): bool
    {
        return $this->isOwnItem($user, $receivedMail);
    }

    /**
     * Determine whether the user can create models.
     *
     * @return Response|bool
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the model.
     *
     * @return Response|bool
     */
    public function update(User $user, ReceivedMail $receivedMail): bool
    {
        return $this->isOwnItem($user, $receivedMail);
    }

    /**
     * Determine whether the user can delete the model.
     *
     * @return Response|bool
     */
    public function delete(User $user, ReceivedMail $receivedMail): bool
    {
        return $this->isOwnItem($user, $receivedMail);
    }

    /**
     * Determine whether the user can restore the model.
     *
     * @return Response|bool
     */
    public function restore(User $user, ReceivedMail $receivedMail): bool
    {
        return $this->isOwnItem($user, $receivedMail);
    }

    /**
     * Determine whether the user can permanently delete the model.
     *
     * @return Response|bool
     */
    public function forceDelete(User $user, ReceivedMail $receivedMail): bool
    {
        return $this->isOwnItem($user, $receivedMail);
    }
}
