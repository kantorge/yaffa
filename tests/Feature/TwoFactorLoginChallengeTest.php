<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class TwoFactorLoginChallengeTest extends TestCase
{
    use RefreshDatabase;

    private function enableTwoFactorFor(User $user): void
    {
        $user->createTwoFactorAuth();
        $user->confirmTwoFactorAuth($user->fresh()->makeTwoFactorCode());
    }

    /**
     * The test client does not carry Set-Cookie headers between separate calls the way a real
     * browser would, so the session cookie (holding laragear/two-factor's flashed, encrypted
     * credentials) has to be forwarded explicitly between the two requests of a login challenge.
     */
    private function forwardSessionCookie(TestResponse $response): void
    {
        $cookieName = config('session.cookie');

        foreach ($response->headers->getCookies() as $cookie) {
            if ($cookie->getName() === $cookieName) {
                $this->withUnencryptedCookie($cookieName, $cookie->getValue());

                return;
            }
        }
    }

    public function test_login_without_two_factor_enabled_is_unchanged(): void
    {
        $user = User::factory()->create(['password' => Hash::make('correct-password')]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'correct-password',
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect('/');
    }

    public function test_login_with_two_factor_enabled_shows_challenge_instead_of_completing_login(): void
    {
        $user = User::factory()->create(['password' => Hash::make('correct-password')]);
        $this->enableTwoFactorFor($user);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'correct-password',
        ]);

        $this->assertGuest();
        $response->assertViewIs('auth.two-factor-challenge');
    }

    public function test_login_with_wrong_password_is_rejected_even_with_two_factor_enabled(): void
    {
        $user = User::factory()->create(['password' => Hash::make('correct-password')]);
        $this->enableTwoFactorFor($user);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('email');
    }

    public function test_challenge_with_wrong_code_is_rejected(): void
    {
        $user = User::factory()->create(['password' => Hash::make('correct-password')]);
        $this->enableTwoFactorFor($user);

        $firstResponse = $this->post('/login', [
            'email' => $user->email,
            'password' => 'correct-password',
        ]);
        $this->forwardSessionCookie($firstResponse);

        $response = $this->post('/login', [
            '2fa_code' => '000000',
        ]);

        $this->assertGuest();
        $response->assertViewIs('auth.two-factor-challenge');
        // The challenge view is rendered directly (not via redirect), so laragear/two-factor
        // attaches errors as a view variable (View::withErrors()) rather than flashing them
        // to the session (which is how RedirectResponse::withErrors() behaves).
        $this->assertTrue($response->viewData('errors')->has('2fa_code'));
    }

    public function test_challenge_with_correct_code_completes_login(): void
    {
        $user = User::factory()->create(['password' => Hash::make('correct-password')]);
        $this->enableTwoFactorFor($user);

        $firstResponse = $this->post('/login', [
            'email' => $user->email,
            'password' => 'correct-password',
        ]);
        $this->forwardSessionCookie($firstResponse);

        // Advance past the TOTP period already consumed by enableTwoFactorFor()'s confirmation
        // code, so makeTwoFactorCode() here doesn't regenerate that same (now-replayed) code.
        $this->travel(31)->seconds();
        $code = $user->fresh()->makeTwoFactorCode();

        $response = $this->post('/login', [
            '2fa_code' => $code,
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect('/');
    }

    public function test_challenge_accepts_a_recovery_code_and_consumes_it(): void
    {
        $user = User::factory()->create(['password' => Hash::make('correct-password')]);
        $this->enableTwoFactorFor($user);
        $recoveryCode = $user->fresh()->getRecoveryCodes()->first()['code'];

        $firstResponse = $this->post('/login', [
            'email' => $user->email,
            'password' => 'correct-password',
        ]);
        $this->forwardSessionCookie($firstResponse);

        $response = $this->post('/login', [
            '2fa_code' => $recoveryCode,
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect('/');

        auth()->logout();

        // The same recovery code cannot be used a second time.
        $thirdResponse = $this->post('/login', [
            'email' => $user->email,
            'password' => 'correct-password',
        ]);
        $this->forwardSessionCookie($thirdResponse);

        $secondAttempt = $this->post('/login', [
            '2fa_code' => $recoveryCode,
        ]);

        $this->assertGuest();
        $secondAttempt->assertViewIs('auth.two-factor-challenge');
        $this->assertTrue($secondAttempt->viewData('errors')->has('2fa_code'));
    }
}
