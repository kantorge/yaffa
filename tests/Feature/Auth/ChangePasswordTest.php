<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ChangePasswordTest extends TestCase
{
    use RefreshDatabase;

    private function createUser(): User
    {
        return User::factory()->create([
            'language' => 'en',
            'password' => Hash::make('password'),
        ]);
    }

    public function test_incorrect_current_password_is_rejected(): void
    {
        $user = $this->createUser();

        $response = $this->actingAs($user)->patchJson(route('api.v1.users.me.password'), [
            'current_password' => 'incorrect',
            'password' => 'newPassword123',
            'password_confirmation' => 'newPassword123',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['current_password']);
        $this->assertSame('The password is incorrect.', $response->json('errors.current_password.0'));
    }

    public function test_too_short_new_password_is_rejected(): void
    {
        $user = $this->createUser();

        $response = $this->actingAs($user)->patchJson(route('api.v1.users.me.password'), [
            'current_password' => 'password',
            'password' => 'short',
            'password_confirmation' => 'short',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['password']);
        $this->assertSame('The password must be at least 8 characters.', $response->json('errors.password.0'));
    }

    public function test_password_confirmation_mismatch_is_rejected(): void
    {
        $user = $this->createUser();

        $response = $this->actingAs($user)->patchJson(route('api.v1.users.me.password'), [
            'current_password' => 'password',
            'password' => 'password123',
            'password_confirmation' => 'password1234',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['password']);
        $this->assertSame('The password confirmation does not match.', $response->json('errors.password.0'));
    }

    public function test_user_can_change_password_with_valid_data(): void
    {
        $user = $this->createUser();

        $response = $this->actingAs($user)->patchJson(route('api.v1.users.me.password'), [
            'current_password' => 'password',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertOk();
        $this->assertTrue(Hash::check('password123', $user->fresh()->password));
    }

    public function test_changing_password_without_password_confirmation_returns_error(): void
    {
        $user = $this->createUser();

        $response = $this->actingAs($user)->patchJson(route('api.v1.users.me.password'), [
            'current_password' => 'password',
            'password' => 'newPassword123',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['password']);
    }

    /**
     * Sandbox mode is enabled explicitly, since the test environment does not enable it by default.
     */
    public function test_password_change_in_sandbox_mode_is_not_allowed(): void
    {
        config(['yaffa.sandbox_mode' => true]);

        $user = $this->createUser();

        $response = $this->actingAs($user)->patchJson(route('api.v1.users.me.password'), [
            'current_password' => 'password',
            'password' => 'newPassword123',
            'password_confirmation' => 'newPassword123',
        ]);

        $response->assertStatus(403);
        $response->assertJson([
            'message' => __('This action is not allowed in sandbox mode.'),
        ]);
    }
}
