<?php

namespace App\Policies;

use App\Models\AiHint;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class AiHintPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, AiHint $aiHint): bool
    {
        return $user->id === $aiHint->user_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, AiHint $aiHint): bool
    {
        return $user->id === $aiHint->user_id;
    }

    public function delete(User $user, AiHint $aiHint): bool
    {
        return $user->id === $aiHint->user_id;
    }
}
