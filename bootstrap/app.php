<?php

use App\Providers\AppServiceProvider;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withProviders()
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        channels: __DIR__ . '/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->redirectUsersTo(AppServiceProvider::HOME);

        $middleware->web([
            \Illuminate\Session\Middleware\AuthenticateSession::class,
            \App\Http\Middleware\SetLocale::class,
        ]);

        $middleware->statefulApi();
        $middleware->api(append: [
            \App\Http\Middleware\SetLocale::class,
            'throttle:api',
        ]);

        $middleware->alias([
            'auth' => \App\Http\Middleware\Authenticate::class,
            'bindings' => \Illuminate\Routing\Middleware\SubstituteBindings::class,
            'abilities' => \Laravel\Sanctum\Http\Middleware\CheckAbilities::class,
            'ability' => \Laravel\Sanctum\Http\Middleware\CheckForAnyAbility::class,
            'idempotent' => \App\Http\Middleware\EnsureIdempotent::class,
        ]);

        $middleware->preventRequestForgery(except: [
            '/telescope/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
        $exceptions->render(function (AuthorizationException $e, Request $request) {
            if ($request->is('api/v1/*')) {
                return response()->json([
                    'error' => [
                        'code' => 'FORBIDDEN',
                        'message' => 'This action is unauthorized.',
                    ],
                ], 403);
            }
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/v1/*')) {
                return response()->json([
                    'error' => [
                        'code' => 'UNAUTHENTICATED',
                        'message' => 'Unauthenticated.',
                    ],
                ], 401);
            }
        });

        $exceptions->render(function (ModelNotFoundException $e, Request $request) {
            if ($request->is('api/v1/*')) {
                return response()->json([
                    'error' => [
                        'code' => 'NOT_FOUND',
                        'message' => 'The requested resource was not found.',
                    ],
                ], 404);
            }
        });

        // The 'message' and 'errors' keys are kept next to the 'error' envelope for backward compatibility.
        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->is('api/v1/*')) {
                return response()->json([
                    'message' => $e->getMessage(),
                    'error' => [
                        'code' => 'VALIDATION_ERROR',
                        'message' => $e->getMessage(),
                    ],
                    'errors' => $e->errors(),
                ], $e->status);
            }
        });

        $exceptions->render(function (PostTooLargeException $e, Request $request) {
            if ($request->is('api/v1/*')) {
                return response()->json([
                    'error' => [
                        'code' => 'PAYLOAD_TOO_LARGE',
                        'message' => 'The uploaded data is too large.',
                        'limit_mb' => \App\Services\UploadLimitService::maxFileMb(),
                    ],
                ], 413);
            }
        });

        $exceptions->render(function (HttpException $e, Request $request) {
            if ($request->is('api/v1/*')) {
                $status = $e->getStatusCode();
                $message = $e->getMessage() ?: (Response::$statusTexts[$status] ?? 'Error');

                return response()->json([
                    'message' => $message,
                    'error' => [
                        'code' => match ($status) {
                            400 => 'BAD_REQUEST',
                            403 => 'FORBIDDEN',
                            404 => 'NOT_FOUND',
                            405 => 'METHOD_NOT_ALLOWED',
                            409 => 'CONFLICT',
                            429 => 'TOO_MANY_REQUESTS',
                            default => 'HTTP_ERROR',
                        },
                        'message' => $message,
                    ],
                ], $status, $e->getHeaders());
            }
        });
    })->create();
