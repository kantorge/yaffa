<?php

namespace Tests\Unit\Services;

use App\Models\User;
use App\Services\ApiTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Tests\TestCase;

class ApiTokenServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_rejects_empty_abilities(): void
    {
        $user = User::factory()->create();
        $service = new ApiTokenService();

        $this->expectException(InvalidArgumentException::class);

        $service->create($user, 'Test token', [], null);
    }

    public function test_create_caps_expiry_at_configured_max_lifetime(): void
    {
        config(['yaffa.api_token_max_lifetime_days' => 30]);

        $user = User::factory()->create();
        $service = new ApiTokenService();

        $newToken = $service->create($user, 'Test token', ['accounts:read'], Carbon::now()->addDays(365));

        $this->assertTrue(
            $newToken->accessToken->expires_at->lessThanOrEqualTo(Carbon::now()->addDays(30)->addMinute())
        );
    }

    public function test_create_defaults_to_max_lifetime_when_no_expiry_given(): void
    {
        config(['yaffa.api_token_max_lifetime_days' => 30]);

        $user = User::factory()->create();
        $service = new ApiTokenService();

        $newToken = $service->create($user, 'Test token', ['accounts:read'], null);

        $this->assertTrue(
            $newToken->accessToken->expires_at->between(
                Carbon::now()->addDays(29),
                Carbon::now()->addDays(30)->addMinute()
            )
        );
    }

    public function test_list_only_returns_tokens_for_given_user(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $user->createToken('mine', ['accounts:read']);
        $otherUser->createToken('theirs', ['accounts:read']);

        $service = new ApiTokenService();

        $tokens = $service->list($user);

        $this->assertCount(1, $tokens);
        $this->assertSame('mine', $tokens->first()->name);
    }

    public function test_revoke_deletes_token_owned_by_user(): void
    {
        $user = User::factory()->create();
        $newToken = $user->createToken('mine', ['accounts:read']);

        $service = new ApiTokenService();

        $this->assertTrue($service->revoke($user, $newToken->accessToken->id));
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $newToken->accessToken->id]);
    }

    public function test_revoke_returns_false_for_token_owned_by_another_user(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $newToken = $otherUser->createToken('theirs', ['accounts:read']);

        $service = new ApiTokenService();

        $this->assertFalse($service->revoke($user, $newToken->accessToken->id));
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $newToken->accessToken->id]);
    }
}
