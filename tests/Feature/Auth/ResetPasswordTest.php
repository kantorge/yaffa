<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class ResetPasswordTest extends TestCase
{
    use RefreshDatabase;

    protected function getValidToken($user)
    {
        return Password::broker()->createToken($user);
    }

    protected function getInvalidToken()
    {
        return 'invalid-token';
    }

    protected function passwordResetGetRoute($token)
    {
        return route('password.reset', $token);
    }

    protected function passwordResetPostRoute()
    {
        return '/password/reset';
    }

    protected function successfulPasswordResetRoute()
    {
        return route('home');
    }

    /** @test */
    public function test_user_can_view_password_reset_form()
    {
        $user = User::factory()->create();

        $response = $this->get($this->passwordResetGetRoute($token = $this->getValidToken($user)));

        $response->assertSuccessful();
        $response->assertViewIs('auth.passwords.reset');
        $response->assertViewHas('token', $token);
    }

    /** @test */
    public function test_user_can_view_password_reset_form_when_authenticated()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get($this->passwordResetGetRoute($token = $this->getValidToken($user)));

        $response->assertSuccessful();
        $response->assertViewIs('auth.passwords.reset');
        $response->assertViewHas('token', $token);
    }

    /** @test */
    public function test_user_can_reset_password_with_valid_token()
    {
        Event::fake();
        $user = User::factory()->create();
        $password = 'new-awesome-password';

        $response = $this->post($this->passwordResetPostRoute(), [
            'token' => $this->getValidToken($user),
            'email' => $user->email,
            'password' => $password,
            'password_confirmation' => $password,
        ]);

        $response->assertRedirect($this->successfulPasswordResetRoute());
        $this->assertEquals($user->email, $user->fresh()->email);
        $this->assertTrue(Hash::check($password, $user->fresh()->password));
        $this->assertAuthenticatedAs($user);
        Event::assertDispatched(PasswordReset::class, fn ($e) => $e->user->id === $user->id);
    }

    /** @test */
    public function test_user_cannot_reset_password_with_invalid_token()
    {
        $oldPassword = 'old-password';
        $newPassword = 'new-awesome-password';

        $user = User::factory()->create([
            'password' => Hash::make($oldPassword),
        ]);

        $response = $this
            ->from($this->passwordResetGetRoute($this->getInvalidToken()))
            ->post($this->passwordResetPostRoute(), [
                'token' => $this->getInvalidToken(),
                'email' => $user->email,
                'password' => $newPassword,
                'password_confirmation' => $newPassword,
            ]);

        $response->assertRedirect($this->passwordResetGetRoute($this->getInvalidToken()));
        $this->assertEquals($user->email, $user->fresh()->email);
        $this->assertTrue(Hash::check($oldPassword, $user->fresh()->password));
        $this->assertGuest();
    }

    /** @test */
    public function test_user_cannot_reset_password_without_providing_new_password()
    {
        $oldPassword = 'old-password';

        $user = User::factory()->create([
            'password' => Hash::make($oldPassword),
        ]);

        $response = $this
            ->from($this->passwordResetGetRoute($token = $this->getValidToken($user)))
            ->post($this->passwordResetPostRoute(), [
                'token' => $token,
                'email' => $user->email,
                'password' => '',
                'password_confirmation' => '',
            ]);

        $response->assertRedirect($this->passwordResetGetRoute($token));
        $response->assertSessionHasErrors('password');
        $this->assertTrue(session()->hasOldInput('email'));
        $this->assertFalse(session()->hasOldInput('password'));
        $this->assertEquals($user->email, $user->fresh()->email);
        $this->assertTrue(Hash::check($oldPassword, $user->fresh()->password));
        $this->assertGuest();
    }

    /** @test */
    public function test_user_cannot_reset_password_without_providing_email()
    {
        $oldPassword = 'old-password';
        $newPassword = 'new-awesome-password';

        $user = User::factory()->create([
            'password' => Hash::make($oldPassword),
        ]);

        $response = $this->from($this->passwordResetGetRoute($token = $this->getValidToken($user)))->post($this->passwordResetPostRoute(), [
            'token' => $token,
            'email' => '',
            'password' => $newPassword,
            'password_confirmation' => $newPassword,
        ]);

        $response->assertRedirect($this->passwordResetGetRoute($token));
        $response->assertSessionHasErrors('email');
        $this->assertFalse(session()->hasOldInput('password'));
        $this->assertEquals($user->email, $user->fresh()->email);
        $this->assertTrue(Hash::check($oldPassword, $user->fresh()->password));
        $this->assertGuest();
    }
}
