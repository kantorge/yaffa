<?php

namespace Tests\Feature\API;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TwoFactorDisableTest extends TestCase
{
    use RefreshDatabase;

    private function enableTwoFactorFor(User $user): void
    {
        $user->createTwoFactorAuth();
        $user->confirmTwoFactorAuth($user->fresh()->makeTwoFactorCode());
    }

    public function test_disable_with_wrong_password_is_rejected(): void
    {
        $user = User::factory()->create(['password' => Hash::make('correct-password')]);
        $this->enableTwoFactorFor($user);
        Sanctum::actingAs($user, ['*']);

        $response = $this->postJson(route('api.v1.users.me.two-factor.disable'), [
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertTrue($user->fresh()->hasTwoFactorEnabled());
    }

    public function test_disable_with_correct_password_disables_two_factor(): void
    {
        $user = User::factory()->create(['password' => Hash::make('correct-password')]);
        $this->enableTwoFactorFor($user);
        Sanctum::actingAs($user, ['*']);

        $response = $this->postJson(route('api.v1.users.me.two-factor.disable'), [
            'password' => 'correct-password',
        ]);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJson(['enabled' => false]);
        $this->assertFalse($user->fresh()->hasTwoFactorEnabled());
    }

    public function test_regenerate_recovery_codes_with_wrong_password_is_rejected(): void
    {
        $user = User::factory()->create(['password' => Hash::make('correct-password')]);
        $this->enableTwoFactorFor($user);
        Sanctum::actingAs($user, ['*']);

        $response = $this->postJson(route('api.v1.users.me.two-factor.recovery-codes'), [
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function test_regenerate_recovery_codes_with_correct_password_returns_new_codes(): void
    {
        $user = User::factory()->create(['password' => Hash::make('correct-password')]);
        $this->enableTwoFactorFor($user);
        $originalCodes = $user->fresh()->getRecoveryCodes()->pluck('code')->values()->all();
        Sanctum::actingAs($user, ['*']);

        $response = $this->postJson(route('api.v1.users.me.two-factor.recovery-codes'), [
            'password' => 'correct-password',
        ]);

        $response->assertStatus(Response::HTTP_OK);
        $newCodes = $response->json('recovery_codes');
        $this->assertIsArray($newCodes);
        $this->assertNotEmpty($newCodes);
        $this->assertNotEquals($originalCodes, $newCodes);
    }
}
