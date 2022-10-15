<?php

namespace App\Http\Controllers;

use App\Http\Requests\UserRequest;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function settings()
    {
        return view(
            'user.settings',
            [
                'languages' => config('app.available_languages'),
                'locales' => config('app.available_locales'),
            ]
        );
    }

    public function update(UserRequest $request)
    {
        $validated = $request->validated();

        Auth::user()->fill($validated)
            ->save();

        self::addSimpleSuccessMessage(__('User settings updated'));

        return redirect()->back(); // TODO: where to return?
    }
}
