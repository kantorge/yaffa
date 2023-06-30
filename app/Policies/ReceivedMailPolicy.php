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
     * @param User $user
     * @return Response|bool
     */
    public function viewAny(User $user)
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     *
     * @param User $user
     * @param  ReceivedMail $receivedMail
     * @return Response|bool
     */
    public function view(User $user, ReceivedMail $receivedMail)
    {
        return $this->isOwnItem($user, $receivedMail);
    }

    /**
     * Determine whether the user can create models.
     *
     * @param User $user
     * @return Response|bool
     */
    public function create(User $user)
    {
        return true;
    }

    /**
     * Determine whether the user can update the model.
     *
     * @param User $user
     * @param  ReceivedMail $receivedMail
     * @return Response|bool
     */
    public function update(User $user, ReceivedMail $receivedMail)
    {
        return $this->isOwnItem($user, $receivedMail);
    }

    /**
     * Determine whether the user can delete the model.
     *
     * @param User $user
     * @param  ReceivedMail $receivedMail
     * @return Response|bool
     */
    public function delete(User $user, ReceivedMail $receivedMail)
    {
        return $this->isOwnItem($user, $receivedMail);
    }

    /**
     * Determine whether the user can restore the model.
     *
     * @param User $user
     * @param  ReceivedMail $receivedMail
     * @return Response|bool
     */
    public function restore(User $user, ReceivedMail $receivedMail)
    {
        return $this->isOwnItem($user, $receivedMail);
    }

    /**
     * Determine whether the user can permanently delete the model.
     *
     * @param User $user
     * @param  ReceivedMail $receivedMail
     * @return Response|bool
     */
    public function forceDelete(User $user, ReceivedMail $receivedMail)
    {
        return $this->isOwnItem($user, $receivedMail);
    }
}
