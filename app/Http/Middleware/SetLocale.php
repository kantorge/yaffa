<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class SetLocale
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response|RedirectResponse)  $next
     */
    public function handle(Request $request, Closure $next): \Symfony\Component\HttpFoundation\Response
    {
        // Locale is determined primarily by the user setting
        if ($request->user() && $request->user()->locale) {
            app()->setLocale($request->user()->language);
            return $next($request);
        }

        // Alternatively user can set this on the UI, which is also stored in session
        $lang = $request->get('language');
        if ($lang && array_key_exists($lang, config('app.available_languages'))) {
            app()->setLocale($lang);
            $request->session()->put('language', $lang);
            return $next($request);
        }

        if (session('language')) {
            app()->setLocale(session('language'));
        }

        return $next($request);
    }
}
