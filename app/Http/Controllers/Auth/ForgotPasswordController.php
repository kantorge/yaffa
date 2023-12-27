<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\SendsPasswordResetEmails;
use Illuminate\Http\Request;

class ForgotPasswordController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Password Reset Controller
    |--------------------------------------------------------------------------
    |
    | This controller is responsible for handling password reset emails and
    | includes a trait which assists in sending these notifications from
    | your application to your users. Feel free to explore this trait.
    |
    */

    use SendsPasswordResetEmails;

    /**
     * Override the default validation rules to optionally add the recaptcha validation
     */
    protected function validateEmail(Request $request): void
    {
        $rules = ['email' => 'required|email'];

        if (config('recaptcha.api_site_key')
            && config('recaptcha.api_secret_key')) {
            $rules[recaptchaFieldName()] = recaptchaRuleName();
        }

        $request->validate($rules);
    }
}
