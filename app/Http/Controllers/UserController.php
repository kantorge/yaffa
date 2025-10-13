<?php

namespace App\Http\Controllers;

use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Laracasts\Utilities\JavaScript\JavaScriptFacade as JavaScript;

class UserController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            ['auth', 'verified'],
        ];
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
            'datePresets' => config('yaffa.account_date_presets'),
        ]);
        return view('user.settings');
    }
}
