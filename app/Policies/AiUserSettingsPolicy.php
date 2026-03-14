<?php

namespace App\Policies;

use App\Models\AiUserSettings;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class AiUserSettingsPolicy
{
    use HandlesAuthorization;

    private function isOwnItem(User $user, AiUserSettings $settings): bool
    {
        return $user->id === $settings->user_id;
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, AiUserSettings $settings): bool
    {
        return $this->isOwnItem($user, $settings);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, AiUserSettings $settings): bool
    {
        return $this->isOwnItem($user, $settings);
    }
}
