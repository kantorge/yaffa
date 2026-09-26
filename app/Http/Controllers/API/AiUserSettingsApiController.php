<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\AiUserSettingsRequest;
use App\Http\Resources\AiUserSettingsResource;
use App\Models\User;
use App\Services\AiUserSettingsResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Middleware;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

#[Middleware('auth:sanctum')]
#[Middleware('verified')]
#[Middleware('abilities:settings', only: [
    'show', 'update',
])]
class AiUserSettingsApiController extends Controller
{
    public function __construct(
        private AiUserSettingsResolver $settingsResolver
    ) {
    }

    /**
     * Get AI settings
     *
     * Resolves the current user's effective AI settings, creating a default
     * settings record for the user if one does not already exist.
     */
    public function show(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $settings = $this->settingsResolver->getOrCreateForUser($user);

        Gate::authorize('view', $settings);

        $resolved = $this->settingsResolver->resolveFromSettings($user, $settings);

        return response()->json((new AiUserSettingsResource($resolved))->resolve(), Response::HTTP_OK);
    }

    /**
     * Update AI settings
     *
     * Updates the current user's AI settings. This feature is not available
     * in sandbox mode.
     */
    public function update(AiUserSettingsRequest $request): JsonResponse
    {
        // This feature is not enabled in sandbox mode
        if (config('yaffa.sandbox_mode')) {
            return response()->json([
                'message' => 'This feature is not available in sandbox mode.',
            ], Response::HTTP_FORBIDDEN);
        }

        /** @var User $user */
        $user = $request->user();

        $settings = $this->settingsResolver->getOrCreateForUser($user);

        Gate::authorize('update', $settings);

        $updatedSettings = $this->settingsResolver->updateForUser($user, $request->validated());
        $resolved = $this->settingsResolver->resolveFromSettings($user, $updatedSettings);

        return response()->json((new AiUserSettingsResource($resolved))->resolve(), Response::HTTP_OK);
    }
}
