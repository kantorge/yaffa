<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

/**
 * Routes that are deliberately reachable without authentication.
 */
const PUBLIC_API_ROUTES = ['api.v1.meta'];

/**
 * @return array<int, array{string, string}> [method, uri] for every api/v1 route
 */
function apiV1Routes(): array
{
    $routes = [];

    foreach (Route::getRoutes() as $route) {
        if (! str_starts_with($route->uri(), 'api/v1/') || in_array($route->getName(), PUBLIC_API_ROUTES, true)) {
            continue;
        }

        $method = collect($route->methods())->first(fn (string $m) => ! in_array($m, ['HEAD', 'OPTIONS'], true));
        // Use a value that satisfies the route's own constraints, otherwise routing 404s before auth runs
        $uri = preg_replace_callback('/\{(\w+)\??\}/', function (array $m) use ($route) {
            $constraint = $route->wheres[$m[1]] ?? null;

            return $constraint !== null && preg_match('/^[\w|]+$/', $constraint) ? explode('|', $constraint)[0] : '1';
        }, $route->uri());
        $routes[] = [$method, $uri];
    }

    return $routes;
}

it('rejects anonymous requests on every api v1 route', function () {
    $failures = [];

    foreach (apiV1Routes() as [$method, $uri]) {
        $response = $this->json($method, $uri);

        if ($response->getStatusCode() !== 401 || $response->json('error.code') !== 'UNAUTHENTICATED') {
            $failures[] = "{$method} {$uri} => {$response->getStatusCode()}";
        }
    }

    expect($failures)->toBe([]);
});

it('authenticates every api v1 route with a token and no session', function () {
    $user = User::factory()->create();
    $token = $user->createToken('device', ['*'])->plainTextToken;
    $failures = [];

    foreach (apiV1Routes() as [$method, $uri]) {
        $response = $this->withToken($token)->json($method, $uri);

        // Anything but 401 proves the token was accepted; 403/404/422 come from later layers.
        if ($response->getStatusCode() === 401) {
            $failures[] = "{$method} {$uri}";
        }

        // Each request must stand on its own token, not on a session left behind by a previous one.
        $this->app['auth']->forgetGuards();
    }

    expect($failures)->toBe([]);
});
