<?php

namespace App\Policies;

use App\Models\FileImportProfile;
use App\Models\User;

class FileImportProfilePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, FileImportProfile $profile): bool
    {
        return $profile->isSystem() || $profile->isUserOwnedBy($user);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, FileImportProfile $profile): bool
    {
        return $profile->isUserOwnedBy($user);
    }

    public function delete(User $user, FileImportProfile $profile): bool
    {
        return $profile->isUserOwnedBy($user);
    }
}
