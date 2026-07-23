<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\ApiTokenRequest;
use App\Models\User;
use App\Services\ApiTokenService;
use Closure;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\PersonalAccessToken;

class ApiTokenApiController extends Controller implements HasMiddleware
{
    public function __construct(protected ApiTokenService $apiTokenService)
    {
    }

    public static function middleware(): array
    {
        return [
            'auth:sanctum',
            'verified',
            // Token management must stay behind the first-party session: since no controller yet
            // enforces per-token abilities (see the "Phased Ability Enforcement" spec section), a
            // bearer token reaching these endpoints could mint itself a broader-access replacement,
            // defeating the whole point of scoping it in the first place.
            function (Request $request, Closure $next) {
                if ($request->user()?->currentAccessToken() instanceof PersonalAccessToken) {
                    abort(403, 'Managing API tokens is only available to first-party session requests.');
                }

                return $next($request);
            },
        ];
    }

    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $tokens = $this->apiTokenService->list($user)->map(fn ($token) => [
            'id' => $token->id,
            'name' => $token->name,
            'abilities' => $token->abilities,
            'last_used_at' => $token->last_used_at,
            'expires_at' => $token->expires_at,
            'created_at' => $token->created_at,
        ]);

        return response()->json([
            'data' => $tokens,
        ]);
    }

    public function store(ApiTokenRequest $request): JsonResponse
    {
        $validated = $request->validated();

        /** @var User $user */
        $user = $request->user();

        // Defense in depth alongside the session-only middleware() gate above: a token can never
        // grant abilities it does not itself hold. This is a no-op for session requests, whose
        // TransientToken::can() always returns true.
        $currentToken = $user->currentAccessToken();
        $abilities = array_values(array_filter(
            $validated['abilities'],
            fn (string $ability) => $currentToken->can($ability)
        ));

        $newToken = $this->apiTokenService->create(
            $user,
            $validated['name'],
            $abilities,
            isset($validated['expires_at']) ? Carbon::parse($validated['expires_at']) : null
        );

        return response()->json([
            'id' => $newToken->accessToken->id,
            'name' => $newToken->accessToken->name,
            'abilities' => $newToken->accessToken->abilities,
            'expires_at' => $newToken->accessToken->expires_at,
            'token' => $newToken->plainTextToken,
        ], 201);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! $this->apiTokenService->revoke($user, $id)) {
            throw new ModelNotFoundException();
        }

        return response()->json(null, 204);
    }
}
