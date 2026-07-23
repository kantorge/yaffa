<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\ApiTokenRequest;
use App\Models\User;
use App\Services\ApiTokenService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Support\Carbon;

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

        $newToken = $this->apiTokenService->create(
            $user,
            $validated['name'],
            $validated['abilities'],
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
