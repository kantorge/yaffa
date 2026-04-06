<?php

namespace App\Policies;

use App\Models\InvestmentProviderConfig;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class InvestmentProviderConfigPolicy
{
    use HandlesAuthorization;

    private function isOwnItem(User $user, InvestmentProviderConfig $config): bool
    {
        return $user->id === $config->user_id;
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, InvestmentProviderConfig $config): bool
    {
        return $this->isOwnItem($user, $config);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, InvestmentProviderConfig $config): bool
    {
        return $this->isOwnItem($user, $config);
    }

    public function delete(User $user, InvestmentProviderConfig $config): bool
    {
        return $this->isOwnItem($user, $config);
    }
}
