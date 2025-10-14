<?php

namespace App\Http\Middleware;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class Authenticate extends Middleware
{
    /**
     * Handle an unauthenticated user.
     *
     * @param Request $request
     * @param  array  $guards
     *
     * @throws AuthenticationException
     */
    protected function unauthenticated(Request $request, array $guards): void
    {
        if ($request->expectsJson()) {
            abort(Response::HTTP_FORBIDDEN, 'Unauthorized.');
        }

        throw new AuthenticationException(
            'Unauthenticated.',
            $guards,
            $this->redirectTo($request)
        );
    }

    /**
     * Get the path the user should be redirected to when they are not authenticated.
     *
     * @param  Request  $request
     * @return string|null
     */
    protected function redirectTo(Request $request): ?string
    {
        return route('login');
    }
}
