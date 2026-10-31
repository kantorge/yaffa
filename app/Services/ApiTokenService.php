<?php

namespace App\Services;

use App\Enums\ApiTokenAbility;
use App\Models\User;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Database\Eloquent\ModelNotFoundException;
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
     * Creates a read+write token for the mobile app, packaged as a versioned deep link and QR code.
     * The plain-text token is only available here, at creation time.
     *
     * @return array{token: string, deep_link: string, qr_svg: string, warnings: array<string>}
     */
    public function createPairing(User $user, string $name): array
    {
        $this->assertSecureInstanceUrl();

        // No 'settings': a lost phone must not be able to read or change credentials and account security.
        return $this->packagePairing($this->create(
            $user,
            $name,
            [ApiTokenAbility::Read->value, ApiTokenAbility::Write->value],
            null
        ));
    }

    /**
     * Replaces the secret of an existing token (same row, name, abilities, expiry and device) and returns
     * it packaged for pairing. The previous secret stops working immediately.
     *
     * @return array{token: string, deep_link: string, qr_svg: string, warnings: array<string>}
     *
     * @throws ModelNotFoundException
     * @throws InvalidArgumentException when the token is expired, its abilities are not exactly read and write, or APP_URL is not https in production
     */
    public function rotateForPairing(User $user, int $tokenId): array
    {
        $this->assertSecureInstanceUrl();

        /** @var PersonalAccessToken $token */
        $token = $user->tokens()->findOrFail($tokenId);

        if ($token->expires_at !== null && $token->expires_at->isPast()) {
            throw new InvalidArgumentException('This token has expired and cannot be paired with the app.');
        }

        // Exactly read+write: a token with 'settings' (or '*') must never end up on a phone.
        $abilities = $token->abilities;
        sort($abilities);

        if ($abilities !== [ApiTokenAbility::Read->value, ApiTokenAbility::Write->value]) {
            throw new InvalidArgumentException('Only tokens with exactly read and write access can be paired with the app.');
        }

        $secret = $user->generateTokenString();
        $token->forceFill(['token' => hash('sha256', $secret)])->save();

        return $this->packagePairing(new NewAccessToken($token, $token->getKey() . '|' . $secret));
    }

    /**
     * The token travels in the deep link and is then sent on every call, so production must not pair over plain HTTP.
     *
     * @throws InvalidArgumentException
     */
    private function assertSecureInstanceUrl(): void
    {
        if (app()->isProduction() && ! str_starts_with((string) config('app.url'), 'https://')) {
            throw new InvalidArgumentException('Pairing the app requires APP_URL to use https.');
        }
    }

    /**
     * @return array{token: string, deep_link: string, qr_svg: string, warnings: array<string>}
     */
    private function packagePairing(NewAccessToken $newToken): array
    {
        $baseUrl = mb_rtrim((string) config('app.url'), '/');
        $deepLink = 'yaffa://pair?' . http_build_query([
            'v' => 1,
            'url' => $baseUrl,
            'token' => $newToken->plainTextToken,
        ], '', '&', PHP_QUERY_RFC3986);

        $host = (string) parse_url($baseUrl, PHP_URL_HOST);
        $warnings = [];

        if (! str_starts_with($baseUrl, 'https://')) {
            $warnings[] = 'not_https';
        }

        if ($host === 'localhost' || str_starts_with($host, '127.') || $host === '[::1]') {
            $warnings[] = 'localhost';
        }

        $qr = (new Writer(new ImageRenderer(new RendererStyle(300, 2), new SvgImageBackEnd())))
            ->writeString($deepLink);

        return [
            'token' => $newToken->plainTextToken,
            'deep_link' => $deepLink,
            'qr_svg' => $qr,
            'warnings' => $warnings,
        ];
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
