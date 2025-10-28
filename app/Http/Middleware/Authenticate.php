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
     *
     * @throws AuthenticationException
     */
    protected function unauthenticated($request, array $guards): void
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
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    protected function redirectTo(Request $request): ?string
    {
        return route('login');
    }
}
