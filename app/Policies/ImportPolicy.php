<?php

namespace App\Policies;

use App\Models\AccountEntity;
use App\Models\User;

class ImportPolicy
{
    public function parse(User $user, AccountEntity $accountEntity): bool
    {
        return $user->id === $accountEntity->user_id && $accountEntity->isAccount();
    }
}
