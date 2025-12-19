<?php

namespace App\Policies;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Auth\Access\Response;

class TransactionPolicy
{
    use HandlesAuthorization;

    public function isOwnItem(User $user, Transaction $transaction): bool
    {
        return $user->id === $transaction->user_id;
    }

    /**
     * Determine whether the user can view any models.
     *
     *
     * @return Response|bool
     */
    public function viewAny(User $user): Response|bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     *
     *
     * @return Response|bool
     */
    public function view(User $user, Transaction $transaction): Response|bool
    {
        return $this->isOwnItem($user, $transaction);
    }

    /**
     * Determine whether the user can create models.
     *
     *
     * @return Response|bool
     */
    public function create(User $user): Response|bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the model.
     *
     *
     * @return Response|bool
     */
    public function update(User $user, Transaction $transaction): Response|bool
    {
        return $this->isOwnItem($user, $transaction);
    }

    /**
     * Determine whether the user can delete the model.
     *
     *
     * @return Response|bool
     */
    public function delete(User $user, Transaction $transaction): Response|bool
    {
        return $this->isOwnItem($user, $transaction);
    }

    /**
     * Determine whether the user can restore the model.
     *
     *
     * @return Response|bool
     */
    public function restore(User $user, Transaction $transaction): Response|bool
    {
        return $this->isOwnItem($user, $transaction);
    }

    /**
     * Determine whether the user can permanently delete the model.
     *
     *
     * @return Response|bool
     */
    public function forceDelete(User $user, Transaction $transaction): Response|bool
    {
        return $this->isOwnItem($user, $transaction);
    }
}
