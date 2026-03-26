<?php

namespace App\Policies;

use App\Models\AiProviderConfig;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Response-based policy results are intentionally not used yet.
 */
class AiProviderConfigPolicy
{
    use HandlesAuthorization;

    private function isOwnItem(User $user, AiProviderConfig $config): bool
    {
        return $user->id === $config->user_id;
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, AiProviderConfig $config): bool
    {
        return $this->isOwnItem($user, $config);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, AiProviderConfig $config): bool
    {
        return $this->isOwnItem($user, $config);
    }

    public function delete(User $user, AiProviderConfig $config): bool
    {
        return $this->isOwnItem($user, $config);
    }
}
