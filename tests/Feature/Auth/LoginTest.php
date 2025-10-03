<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    protected function successfulLoginRoute()
    {
        return route('home');
    }

    protected function loginGetRoute()
    {
        return route('login');
    }

    protected function loginPostRoute()
    {
        return route('login');
    }

    protected function getTooManyLoginAttemptsMessage()
    {
        return sprintf('/^%s$/', str_replace('\:seconds', '\d+', preg_quote(__('auth.throttle'), '/')));
    }

    /**
     * For all these tests, make sure that recaptcha is disabled.
     */
    protected function setUp(): void
    {
        parent::setUp();

        config(['recaptcha.api_site_key' => null]);
        config(['recaptcha.api_secret_key' => null]);
    }

    /** @test */
    public function unsigned_visitor_can_access_the_login_form(): void
    {
        $response = $this->get($this->loginGetRoute());

        $response->assertSuccessful();
        $response->assertViewIs('auth.login');
    }

    /** @test */
    public function signed_visitor_cannot_access_the_login_form(): void
    {
        $user = User::factory()->make();

        $response = $this->actingAs($user)->get($this->loginGetRoute());

        $response->assertRedirect($this->successfulLoginRoute());
    }

    /** @test */
    public function login_form_displays_validation_errors_on_empty_form_submission(): void
    {
        $response = $this->post(
            $this->loginPostRoute(),
            []
        );

        $response->assertStatus(302);
        $response->assertSessionHasErrors('email');
    }

    /** @test */
    public function user_cannot_log_in_with_incorrect_password(): void
    {
        $password = 'secret';

        $user = User::factory()->create([
            'password' => bcrypt($password),
        ]);

        $response = $this
            ->from($this->loginGetRoute())
            ->post(
                $this->loginPostRoute(),
                [
                    'email' => $user->email,
                    'password' => 'not' . $password,
                ]
            );

        $response->assertRedirect($this->loginGetRoute());
        $response->assertSessionHasErrors('email');
        $this->assertTrue(session()->hasOldInput('email'));
        $this->assertGuest();
    }

    /** @test */
    public function user_cannot_log_in_with_email_that_does_not_exist(): void
    {
        $response = $this
            ->from($this->loginGetRoute())
            ->post($this->loginPostRoute(), [
                'email' => 'nobody@example.com',
                'password' => 'invalid-password',
            ]);

        $response->assertRedirect($this->loginGetRoute());
        $response->assertSessionHasErrors('email');
        $this->assertTrue(session()->hasOldInput('email'));
        $this->assertFalse(session()->hasOldInput('password'));
        $this->assertGuest();
    }

    /** @test */
    public function test_user_cannot_make_too_many_failed_attempts(): void
    {
        $password = 'secret';


        $user = User::factory()->create([
            'password' => bcrypt($password),
        ]);

        foreach (range(0, 5) as $_) {
            $response = $this
                ->from($this->loginGetRoute())
                ->post($this->loginPostRoute(), [
                    'email' => $user->email,
                    'password' => 'not' . $password,
                ]);
        }

        $response->assertRedirect($this->loginGetRoute());
        $response->assertSessionHasErrors('email');

        /** @var string $throttleError */
        $throttleError = collect(
            $response
                ->baseResponse
                ->getSession()
                ->get('errors')
                ->getBag('default')
                ->get('email')
        )->first();

        $this->assertMatchesRegularExpression(
            $this->getTooManyLoginAttemptsMessage(),
            $throttleError
        );

        $this->assertTrue(session()->hasOldInput('email'));
        $this->assertFalse(session()->hasOldInput('password'));
        $this->assertGuest();
    }

    /** @test */
    public function user_can_log_in_with_correct_credentials(): void
    {
        $password = 'secret';

        $user = User::factory()->create([
            'password' => bcrypt($password),
        ]);

        $response = $this->post(
            $this->loginPostRoute(),
            [
                'email' => $user->email,
                'password' => $password,
            ]
        );

        $response->assertRedirect($this->successfulLoginRoute());
        $this->assertAuthenticatedAs($user);
    }
}
