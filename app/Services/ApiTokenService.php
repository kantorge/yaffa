<?php

namespace App\Services;

use App\Enums\ApiTokenAbility;
use App\Models\User;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
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
     * Creates a full-access token for the mobile app, packaged as a versioned deep link and QR code.
     * The plain-text token is only available here, at creation time.
     *
     * @return array{token: string, deep_link: string, qr_svg: string, warnings: array<string>}
     */
    public function createPairing(User $user, string $name): array
    {
        $newToken = $this->create($user, $name, ApiTokenAbility::values(), null);

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
