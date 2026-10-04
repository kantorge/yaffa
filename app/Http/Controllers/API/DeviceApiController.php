<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\DeviceRequest;
use App\Models\Device;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Middleware;
use Laravel\Sanctum\PersonalAccessToken;

#[Middleware('auth:sanctum')]
#[Middleware('verified')]
#[Middleware('abilities:write', only: ['store', 'destroy'])]
class DeviceApiController extends Controller
{
    /**
     * Register a push endpoint
     *
     * Binds a UnifiedPush URL or FCM token to the calling device token, replacing any earlier one.
     * Needs a personal access token: a browser session has no device to bind to.
     */
    public function store(DeviceRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $token = $request->user()?->currentAccessToken();

        if (! $token instanceof PersonalAccessToken) {
            return $this->tokenRequired();
        }

        $device = Device::query()->updateOrCreate(
            ['personal_access_token_id' => $token->id],
            ['user_id' => $user->id, 'type' => $request->validated('type'), 'endpoint' => $request->validated('endpoint')],
        );

        return response()->json(['id' => $device->id, 'type' => $device->type]);
    }

    /**
     * Unregister the push endpoint of the calling device token
     */
    public function destroy(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $token = $request->user()?->currentAccessToken();

        if (! $token instanceof PersonalAccessToken) {
            return $this->tokenRequired();
        }

        Device::query()->where('personal_access_token_id', $token->id)->delete();

        return response()->json(null, 204);
    }

    private function tokenRequired(): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => 'TOKEN_REQUIRED',
                'message' => 'Push endpoints can only be registered by a device using an API token.',
            ],
        ], 422);
    }
}
