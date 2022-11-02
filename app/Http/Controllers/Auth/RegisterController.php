<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Providers\RouteServiceProvider;
use Illuminate\Foundation\Auth\RegistersUsers;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

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

    /**
     * Show the application registration form.
     * Overwrite default behavior by limiting number of users allowed.
     *
     * @return \Illuminate\View\View
     */
    public function showRegistrationForm()
    {
        /**
         * @get('/register')
         * @name('register')
         * @middlewares('web', 'guest')
         */
        if (config('yaffa.registered_user_limit') && User::count() >= config('yaffa.registered_user_limit')) {
            self::addMessage(
                'You cannot register new users.',
                'danger',
                'User limit reached',
                'exclamation-triangle'
            );

            return redirect()->route('login');
        }

        return view(
            'auth.register',
            [
                'languages' => config('app.available_languages'),
                'locales' => config('app.available_locales'),
            ]
        );
    }

    /**
     * Where to redirect users after registration.
     *
     * @var string
     */
    protected $redirectTo = RouteServiceProvider::HOME;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest');
    }

    /**
     * Get a validator for an incoming registration request.
     *
     * @param  array  $data
     * @return \Illuminate\Contracts\Validation\Validator
     */
    protected function validator(array $data)
    {
        return Validator::make($data, [
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
                'string',
                'min:8',
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
        ]);
    }

    /**
     * Create a new user instance after a valid registration.
     *
     * @param  array  $data
     * @return \App\Models\User
     */
    protected function create(array $data)
    {
        return User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'language' => $data['language'],
            'locale' => $data['locale'],
        ]);
    }
}
