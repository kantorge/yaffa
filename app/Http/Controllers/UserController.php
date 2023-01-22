<?php

namespace App\Http\Controllers;

use App\Http\Requests\UserRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

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
        return view(
            'user.settings',
            [
                'languages' => config('app.available_languages'),
                'locales' => config('app.available_locales'),
            ]
        );
    }

    public function update(UserRequest $request): RedirectResponse
    {
        /**
         * @patch('/user/settings')
         * @name('user.update')
         * @middlewares('web', 'auth', 'verified')
         */
        $validated = $request->validated();

        Auth::user()
            ->fill($validated)
            ->save();

        self::addSimpleSuccessMessage(__('User settings updated'));

        return redirect()->back(); // TODO: where to return the user?
    }
}
