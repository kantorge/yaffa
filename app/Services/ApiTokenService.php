<?php

namespace App\Services;

use App\Models\User;
use InvalidArgumentException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Laravel\Sanctum\NewAccessToken;
use Laravel\Sanctum\PersonalAccessToken;

class ApiTokenService
{
    /**
     * @param array<string> $abilities
     */
    public function create(User $user, string $name, array $abilities, ?Carbon $expiresAt): NewAccessToken
    {
        if ($abilities === []) {
            throw new InvalidArgumentException('A token must be created with at least one ability.');
        }

        $maxExpiresAt = now()->addDays((int) config('yaffa.api_token_max_lifetime_days'));

        if ($expiresAt === null || $expiresAt->greaterThan($maxExpiresAt)) {
            $expiresAt = $maxExpiresAt;
        }

        return $user->createToken($name, $abilities, $expiresAt);
    }

    /**
     * @return Collection<int, PersonalAccessToken>
     */
    public function list(User $user): Collection
    {
        return $user->tokens()->orderByDesc('created_at')->get();
    }

    public function revoke(User $user, int $tokenId): bool
    {
        return (bool) $user->tokens()->where('id', $tokenId)->delete();
    }
}
