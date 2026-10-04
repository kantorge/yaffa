<?php

namespace App\Policies;

use App\Models\TransactionTemplate;
use App\Models\User;

class TransactionTemplatePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, TransactionTemplate $template): bool
    {
        return $user->id === $template->user_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, TransactionTemplate $template): bool
    {
        return $user->id === $template->user_id;
    }

    public function delete(User $user, TransactionTemplate $template): bool
    {
        return $user->id === $template->user_id;
    }
}
