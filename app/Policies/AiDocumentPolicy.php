<?php

namespace App\Policies;

use App\Models\AiDocument;
use App\Models\User;

class AiDocumentPolicy
{
    private function isOwnItem(User $user, AiDocument $aiDocument): bool
    {
        return $user->id === $aiDocument->user_id;
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, AiDocument $aiDocument): bool
    {
        return $this->isOwnItem($user, $aiDocument);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, AiDocument $aiDocument): bool
    {
        return $this->isOwnItem($user, $aiDocument);
    }

    public function delete(User $user, AiDocument $aiDocument): bool
    {
        return $this->isOwnItem($user, $aiDocument);
    }

    public function reprocess(User $user, AiDocument $aiDocument): bool
    {
        return $this->isOwnItem($user, $aiDocument);
    }
}
