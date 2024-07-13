<?php

namespace App\Http\Controllers;

use App\Http\Requests\UserRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Laracasts\Utilities\JavaScript\JavaScriptFacade as JavaScript;

class UserController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'verified']);
    }

    /**
     * @return View
     */
    public function settings(): View
    {
        /**
         * @get('/user/settings')
         * @name('user.settings')
         * @middlewares('web', 'auth', 'verified')
         */

        JavaScript::put([
            'languages' => config('app.available_languages'),
            'locales' => config('app.available_locales'),
        ]);
        return view('user.settings');
    }
}
