<?php

namespace App\Policies;

use App\Models\GocardlessRequisition;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Auth\Access\Response;

class GocardlessRequisitionPolicy
{
    use HandlesAuthorization;

    private function isOwnItem(User $user, GocardlessRequisition $gocardlessRequisition): bool
    {
        return $user->id === $gocardlessRequisition->user_id;
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
    public function view(User $user, GocardlessRequisition $gocardlessRequisition): bool
    {
        return $this->isOwnItem($user, $gocardlessRequisition);
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
    public function update(User $user, GocardlessRequisition $gocardlessRequisition): bool
    {
        return $this->isOwnItem($user, $gocardlessRequisition);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, GocardlessRequisition $gocardlessRequisition): bool
    {
        return $this->isOwnItem($user, $gocardlessRequisition);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, GocardlessRequisition $gocardlessRequisition): bool
    {
        return $this->isOwnItem($user, $gocardlessRequisition);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, GocardlessRequisition $gocardlessRequisition): bool
    {
        return $this->isOwnItem($user, $gocardlessRequisition);
    }
}
