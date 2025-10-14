<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class VerificationController extends Controller
{
    public function notice(Request $request)
    {
        // TODO: can this be achieved with middlewares?
        $user = $request->user();
        if ($user->hasVerifiedEmail()) {
            return redirect(route('home'));
        }

        return view('auth.verify');
    }

    public function verify(EmailVerificationRequest $request)
    {
        $request->fulfill();

        self::addSimpleSuccessMessage(__('Email address verified'));

        return redirect('/');
    }

    public function send(Request $request): RedirectResponse
    {
        $request->user()->sendEmailVerificationNotification();

        return back()->with('message', __('Verification link sent!'));
    }
}
