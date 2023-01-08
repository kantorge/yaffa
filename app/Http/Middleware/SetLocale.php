<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SetLocale
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {

        // Locale is determined primarily by user setting
        if ($request->user() && $request->user()->language) {
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
