<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class VerificationController extends Controller
{
    public function notice()
    {
        // TODO: can this be achieved with middlewares?
        $user = Auth::user();
        if ($user->hasVerifiedEmail()) {
            return redirect(route('home'));
        }

        return view('auth.verify');
    }

    public function verify(EmailVerificationRequest $request) {
        $request->fulfill();

        self::addSimpleSuccessMessage(__('Email address verified'));

        return redirect('/');
    }

    public function send(Request $request) {
        $request->user()->sendEmailVerificationNotification();

        return back()->with('message', __('Verification link sent!'));
    }
}
