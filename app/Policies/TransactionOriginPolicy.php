<?php

namespace App\Policies;

use App\Models\TransactionOrigin;
use App\Models\User;

class TransactionOriginPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, TransactionOrigin $origin): bool
    {
        return $user->id === $origin->user_id;
    }

    public function update(User $user, TransactionOrigin $origin): bool
    {
        return $user->id === $origin->user_id;
    }
}
