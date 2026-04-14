<?php

namespace App\Policies;

use App\Models\CsvImportProfile;
use App\Models\User;

class CsvImportProfilePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, CsvImportProfile $profile): bool
    {
        return $profile->isSystem() || $profile->isUserOwnedBy($user);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, CsvImportProfile $profile): bool
    {
        return $profile->isUserOwnedBy($user);
    }

    public function delete(User $user, CsvImportProfile $profile): bool
    {
        return $profile->isUserOwnedBy($user);
    }

    public function clone(User $user, CsvImportProfile $profile): bool
    {
        return $this->view($user, $profile);
    }
}
