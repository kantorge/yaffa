<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\AiHintRequest;
use App\Http\Resources\AiHintResource;
use App\Models\AiHint;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Routing\Attributes\Controllers\Middleware;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

#[Middleware('auth:sanctum')]
#[Middleware('verified')]
#[Middleware('abilities:read', only: ['index', 'show'])]
#[Middleware('abilities:write', only: ['store', 'update', 'destroy'])]
class AiHintApiController extends Controller
{
    /**
     * List AI hints
     *
     * @throws AuthorizationException
     */
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        Gate::authorize('viewAny', AiHint::class);

        return response()->json(
            AiHintResource::collection($user->aiHints()->orderBy('name')->get())->resolve(),
            Response::HTTP_OK
        );
    }

    /**
     * Get an AI hint
     */
    #[Authorize('view', 'aiHint')]
    public function show(AiHint $aiHint): JsonResponse
    {
        return response()->json((new AiHintResource($aiHint))->resolve(), Response::HTTP_OK);
    }

    /**
     * Create an AI hint
     *
     * The hint text may contain {account:ID} and {payee:ID} tokens, which are replaced by the current
     * account/payee name when the extraction prompt is built.
     *
     * @throws AuthorizationException
     */
    public function store(AiHintRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        Gate::authorize('create', AiHint::class);

        $aiHint = $user->aiHints()->create($request->validated());

        return response()->json((new AiHintResource($aiHint))->resolve(), Response::HTTP_CREATED);
    }

    /**
     * Update an AI hint
     */
    #[Authorize('update', 'aiHint')]
    public function update(AiHintRequest $request, AiHint $aiHint): JsonResponse
    {
        $aiHint->update($request->validated());

        return response()->json((new AiHintResource($aiHint))->resolve(), Response::HTTP_OK);
    }

    /**
     * Delete an AI hint
     */
    #[Authorize('delete', 'aiHint')]
    public function destroy(AiHint $aiHint): JsonResponse
    {
        $aiHint->delete();

        return response()->json(['aiHint' => $aiHint], Response::HTTP_OK);
    }
}
