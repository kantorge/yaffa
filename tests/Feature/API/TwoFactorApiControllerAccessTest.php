<?php

namespace Tests\Feature\API;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TwoFactorApiControllerAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['yaffa.sandbox_mode' => false]);
    }

    private function enableTwoFactorFor(User $user): void
    {
        $user->createTwoFactorAuth();
        $user->confirmTwoFactorAuth($user->fresh()->makeTwoFactorCode());
    }

    public function test_bearer_token_without_settings_ability_cannot_enroll(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['read', 'write']);

        $response = $this->postJson(route('api.v1.users.me.two-factor.enroll'));

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    public function test_bearer_token_without_settings_ability_cannot_confirm(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['read', 'write']);

        $response = $this->postJson(route('api.v1.users.me.two-factor.confirm'), [
            'code' => '000000',
        ]);

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    public function test_bearer_token_without_settings_ability_cannot_disable(): void
    {
        $user = User::factory()->create(['password' => Hash::make('correct-password')]);
        $this->enableTwoFactorFor($user);
        Sanctum::actingAs($user, ['read', 'write']);

        $response = $this->postJson(route('api.v1.users.me.two-factor.disable'), [
            'password' => 'correct-password',
        ]);

        $response->assertStatus(Response::HTTP_FORBIDDEN);
        $this->assertTrue($user->fresh()->hasTwoFactorEnabled());
    }

    public function test_bearer_token_without_settings_ability_cannot_regenerate_recovery_codes(): void
    {
        $user = User::factory()->create(['password' => Hash::make('correct-password')]);
        $this->enableTwoFactorFor($user);
        Sanctum::actingAs($user, ['read', 'write']);

        $response = $this->postJson(route('api.v1.users.me.two-factor.recovery-codes'), [
            'password' => 'correct-password',
        ]);

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    public function test_bearer_token_with_settings_ability_can_enroll(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['settings']);

        $response = $this->postJson(route('api.v1.users.me.two-factor.enroll'));

        $response->assertStatus(Response::HTTP_OK);
    }

    public function test_bearer_token_without_settings_ability_can_still_read_status(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['read']);

        $response = $this->getJson(route('api.v1.users.me.two-factor.show'));

        $response->assertStatus(Response::HTTP_OK);
    }
}
