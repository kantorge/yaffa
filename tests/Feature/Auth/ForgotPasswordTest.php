<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ForgotPasswordTest extends TestCase
{
    use RefreshDatabase;

    protected function passwordRequestRoute()
    {
        return route('password.request');
    }

    protected function passwordEmailGetRoute()
    {
        return route('password.email');
    }

    protected function passwordEmailPostRoute()
    {
        return route('password.email');
    }

    /** @test */
    public function test_user_can_view_an_email_password_form()
    {
        $response = $this->get($this->passwordRequestRoute());

        $response->assertSuccessful();
        $response->assertViewIs('auth.passwords.email');
    }

    public function test_user_can_view_an_email_password_form_when_authenticated()
    {
        $user = User::factory()->make();

        $response = $this->actingAs($user)->get($this->passwordRequestRoute());

        $response->assertSuccessful();
        $response->assertViewIs('auth.passwords.email');
    }

    /** @test */
    public function test_user_receives_an_email_with_a_password_reset_link()
    {
        Notification::fake();

        $user = User::factory()->create();

        $response = $this
            ->from($this->passwordEmailGetRoute())
            ->post(
                $this->passwordEmailPostRoute(),
                [
                    'email' => $user->email,
                ]
            );

        $token = DB::table('password_resets')->where('email', $user->email)->first();
        $this->assertNotNull($token);

        Notification::assertSentTo($user, ResetPassword::class, fn ($notification, $channels) => Hash::check($notification->token, $token->token) === true);
    }

    /** @test */
    public function test_user_does_not_receive_email_when_not_registered()
    {
        Notification::fake();

        $email = 'nobody@example.com';

        $response = $this
            ->from($this->passwordEmailGetRoute())
            ->post(
                $this->passwordEmailPostRoute(),
                [
                    'email' => $email,
                ]
            );

        $response->assertRedirect($this->passwordEmailGetRoute());
        $response->assertSessionHasErrors('email');
        Notification::assertNotSentTo(User::factory()->make(['email' => $email]), ResetPassword::class);
    }

    /** @test */
    public function test_email_is_required()
    {
        $response = $this->from($this->passwordEmailGetRoute())->post($this->passwordEmailPostRoute(), []);

        $response->assertRedirect($this->passwordEmailGetRoute());
        $response->assertSessionHasErrors('email');
    }

    /** @test */
    public function test_email_needs_to_be_valid_format()
    {
        $response = $this
            ->from($this->passwordEmailGetRoute())
            ->post(
                $this->passwordEmailPostRoute(),
                [
                    'email' => 'invalid-email',
                ]
            );

        $response->assertRedirect($this->passwordEmailGetRoute());
        $response->assertSessionHasErrors('email');
    }
}
