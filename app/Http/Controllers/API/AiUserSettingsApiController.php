<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\AiUserSettingsRequest;
use App\Http\Resources\AiUserSettingsResource;
use App\Models\User;
use App\Services\AiUserSettingsResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class AiUserSettingsApiController extends Controller implements HasMiddleware
{
    public function __construct(
        private AiUserSettingsResolver $settingsResolver
    ) {
    }

    public static function middleware(): array
    {
        return [
            'auth:sanctum',
            'verified',
        ];
    }

    public function show(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $settings = $this->settingsResolver->getOrCreateForUser($user);

        Gate::authorize('view', $settings);

        $resolved = $this->settingsResolver->resolveFromSettings($user, $settings);

        return response()->json((new AiUserSettingsResource($resolved))->resolve(), Response::HTTP_OK);
    }

    public function update(AiUserSettingsRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $settings = $this->settingsResolver->getOrCreateForUser($user);

        Gate::authorize('update', $settings);

        $updatedSettings = $this->settingsResolver->updateForUser($user, $request->validated());
        $resolved = $this->settingsResolver->resolveFromSettings($user, $updatedSettings);

        return response()->json((new AiUserSettingsResource($resolved))->resolve(), Response::HTTP_OK);
    }
}
