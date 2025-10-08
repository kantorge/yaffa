<?php

namespace App\Http\Controllers\Auth;

use App\Providers\AppServiceProvider;
use App\Events\Registered;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Providers\Faker\CurrencyData;
use Illuminate\Foundation\Auth\RegistersUsers;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class RegisterController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Register Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles the registration of new users as well as their
    | validation and creation. By default this controller uses a trait to
    | provide this functionality without requiring any additional code.
    |
    */

    use RegistersUsers;

    // Define options for default assets. (Translation happens in Blade view.)
    private array $defaultAssetOptions = [
        'default' => 'Default',
        'basic' => 'Basic',
        'advanced' => 'Advanced',
        'none' => 'None',
    ];

    private array $availableCurrencies;

    /**
     * Create a new controller instance.
     */
    public function __construct()
    {
        $this->middleware('guest');

        foreach (CurrencyData::getCurrencies() as $currency) {
            $this->availableCurrencies[$currency['iso_code']] = $currency['name'];
        }
    }

    /**
     * Show the application registration form.
     * Overwrite default behavior by limiting number of users allowed.
     *
     * @return View|RedirectResponse
     */
    public function showRegistrationForm(): View|RedirectResponse
    {
        /**
         * @get('/register')
         * @name('register')
         * @middlewares('web', 'guest')
         */
        if (config('yaffa.registered_user_limit') && User::count() >= config('yaffa.registered_user_limit')) {
            self::addMessage(
                __('You cannot register new users.'),
                'danger',
                __('User limit reached'),
                'exclamation-triangle'
            );

            return redirect()->route('login');
        }

        return view(
            'auth.register',
            [
                'languages' => config('app.available_languages'),
                'locales' => config('app.available_locales'),
                'defaultAssetOptions' => $this->defaultAssetOptions,
                'availableCurrencies' => $this->availableCurrencies,
            ]
        );
    }

    /**
     * Where to redirect users after registration.
     *
     * @var string
     */
    protected string $redirectTo = AppServiceProvider::HOME;

    /**
     * Get a validator for an incoming registration request.
     *
     * @param  array  $data
     * @return \Illuminate\Contracts\Validation\Validator
     */
    protected function validator(array $data): \Illuminate\Contracts\Validation\Validator
    {
        $rules = [
            'name' => [
                'required',
                'string',
                'max:255'
            ],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                'unique:users'
            ],
            'password' => [
                'required',
                Password::defaults(),
                'confirmed'
            ],
            'language' => [
                'required',
                Rule::in(array_keys(config('app.available_languages'))),
            ],
            'locale' => [
                'required',
                Rule::in(array_keys(config('app.available_locales'))),
            ],
            'tos' => [
                'accepted'
            ],
            'default_data' => [
                'required',
                Rule::in(array_keys($this->defaultAssetOptions)),
            ],
            'base_currency' => [
                'required',
                Rule::in(array_keys($this->availableCurrencies)),
            ]
        ];

        if (
            config('recaptcha.api_site_key')
            && config('recaptcha.api_secret_key')
        ) {
            $rules[recaptchaFieldName()] = recaptchaRuleName();
        }

        return Validator::make($data, $rules);
    }

    /**
     * Create a new user instance after a valid registration.
     *
     * @param  array  $data
     * @return User
     */
    protected function create(array $data): User
    {
        return User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'language' => $data['language'],
            'locale' => $data['locale'],
        ]);
    }

    /**
     * Handle a registration request for the application.
     * Overwrite default behavior by adding custom parameter to Registered event.
     *
     * @param Request $request
     * @return RedirectResponse|JsonResponse
     */
    public function register(Request $request): JsonResponse|RedirectResponse
    {
        // Enforce optional user limit.
        if (config('yaffa.registered_user_limit') && User::count() >= config('yaffa.registered_user_limit')) {
            if ($request->wantsJson()) {
                return new JsonResponse(
                    [
                        'message' => __('You cannot register new users.'),
                        'title' => __('User limit reached'),
                    ],
                    429
                );
            }
            self::addMessage(
                __('You cannot register new users.'),
                'danger',
                __('User limit reached'),
                'exclamation-triangle'
            );
            return redirect()->route('login');
        }

        $this->validator($request->all())->validate();

        $user = $this->create($request->all());

        // Make the user verified by default, if the feature is enabled.
        if (!config('yaffa.email_verification_required')) {
            $user->markEmailAsVerified();
        }

        event(
            new Registered(
                $user,
                [
                    'defaultData' => $request->post('default_data'),
                    'baseCurrency' => $request->post('base_currency'),
                ]
            )
        );

        // Log the user in after registering.
        $this->guard()->login($user);

        $response = $this->registered($request, $user);
        if ($response) {
            return $response;
        }

        return $request->wantsJson()
            ? new JsonResponse([], 201)
            : redirect($this->redirectPath());
    }
}
