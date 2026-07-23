<?php

namespace App\Http\Controllers\Auth;

use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use App\Providers\AppServiceProvider;
use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Request;
use Laragear\TwoFactor\TwoFactorLoginHelper;

class LoginController extends Controller implements HasMiddleware
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

    public static function middleware(): array
    {
        return [
            new Middleware('guest', except: ['logout']),
            new Middleware('throttle:6,1', only: ['login']),
        ];
    }

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
     */
    protected function attemptLogin(Request $request): bool
    {
        return app(TwoFactorLoginHelper::class)->attemptWhen(
            $this->credentials($request),
            null,
            $request->boolean('remember')
        );
    }
}
