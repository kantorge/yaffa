<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Providers\AppServiceProvider;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Attributes\Controllers\Middleware;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laragear\TwoFactor\TwoFactorLoginHelper;

#[Middleware('guest', except: ['logout'])]
#[Middleware('throttle:6,1', only: ['login'])]
class LoginController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Login Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles authenticating users for the application and
    | redirecting them to your home screen. The controller uses a trait
    | to conveniently provide its functionality to your applications.
    |
    */

    use AuthenticatesUsers;

    /**
     * Where to redirect users after login.
     *
     * @var string
     */
    protected string $redirectTo = AppServiceProvider::HOME;

    /**
     * Override the validateLogin method from AuthenticatesUsers trait to add the recaptcha validation.
     *
     * When a 2FA challenge is pending (laragear/two-factor flashed the original credentials into the
     * session and re-rendered this same route with a code form), only the TOTP/recovery code field is
     * submitted - email/password/recaptcha are intentionally absent from that request.
     */
    protected function validateLogin(Request $request): void
    {
        if ($request->session()->has(config('two-factor.login.key'))) {
            $request->validate([
                '2fa_code' => 'required|string',
            ]);

            return;
        }

        $rules = [
            $this->username() => 'required|string',
            'password' => 'required|string',
        ];

        if (config('recaptcha.api_site_key')
            && config('recaptcha.api_secret_key')) {
            $rules[recaptchaFieldName()] = recaptchaRuleName();
        }

        $request->validate($rules);
    }

    /**
     * Override attemptLogin from AuthenticatesUsers to route the credential check through
     * laragear/two-factor's login helper, which transparently requires a confirmed code
     * when the user has 2FA enabled, and behaves exactly like a normal Auth::attempt()
     * otherwise.
     *
     * Resolved directly from the container (not via the Auth2FA facade) so the helper's
     * Request dependency is always the one for this request, not a facade-cached instance
     * bound to whichever request first resolved it.
     *
     * The 2FA-code step resubmits without the email field (see validateLogin()), so on the
     * initial credential step we remember which account is mid-challenge in the session -
     * throttleKey() falls back to it so ThrottlesLogins' own per-account limiter (already
     * used for wrong-password attempts) also covers wrong-code guesses, not just the shared
     * per-IP `throttle:6,1` route limiter. The helper throws instead of returning false on a
     * wrong code, so login() never reaches its own incrementLoginAttempts() call for that
     * case - we increment it ourselves here.
     */
    protected function attemptLogin(Request $request): bool
    {
        $inChallenge = $request->session()->has(config('two-factor.login.key'));

        if (! $inChallenge) {
            $request->session()->put('2fa_throttle_email', Str::lower((string) $request->input($this->username())));
        }

        try {
            return app(TwoFactorLoginHelper::class)->attemptWhen(
                $this->credentials($request),
                null,
                $request->boolean('remember')
            );
        } catch (HttpResponseException $e) {
            if ($inChallenge) {
                $this->incrementLoginAttempts($request);
            }

            throw $e;
        }
    }

    /**
     * Override to key the 2FA-code step by account alone (no IP component), unlike the
     * inherited "email|ip" default used for the credential step. A 6-digit TOTP code is
     * only 1,000,000 possibilities - an "email|ip" key would let a distributed attacker
     * reset their budget by rotating source IPs, which defeats the point of throttling
     * this specific step (see permissions.md's Risks section). The credential step keeps
     * the inherited per-IP behavior, since that's guarding password guesses, not codes.
     */
    protected function throttleKey(Request $request): string
    {
        if ($request->session()->has(config('two-factor.login.key'))) {
            return '2fa|' . Str::lower((string) $request->session()->get('2fa_throttle_email'));
        }

        return Str::transliterate(Str::lower((string) $request->input($this->username())) . '|' . $request->ip());
    }

    /**
     * Override so the lockout message renders on the field the active step's view actually
     * displays errors for - '2fa_code' during the challenge step, the login field otherwise.
     */
    protected function sendLockoutResponse(Request $request): never
    {
        $seconds = $this->limiter()->availableIn($this->throttleKey($request));

        $field = $request->session()->has(config('two-factor.login.key'))
            ? '2fa_code'
            : $this->username();

        throw ValidationException::withMessages([
            $field => [trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ])],
        ])->status(Response::HTTP_TOO_MANY_REQUESTS);
    }
}
