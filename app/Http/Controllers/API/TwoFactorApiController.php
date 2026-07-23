<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\TwoFactorConfirmRequest;
use App\Http\Requests\TwoFactorPasswordRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;

class TwoFactorApiController extends Controller implements HasMiddleware
{
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

        return response()->json([
            'enabled' => $user->hasTwoFactorEnabled(),
        ]);
    }

    public function enroll(Request $request): JsonResponse
    {
        if (config('yaffa.sandbox_mode')) {
            return response()->json([
                'message' => __('This action is not allowed in sandbox mode.'),
            ], 403);
        }

        /** @var User $user */
        $user = $request->user();

        $secret = $user->createTwoFactorAuth();

        return response()->json([
            'secret' => mb_trim(chunk_split($secret->toString(), 4, ' ')),
            'otpauth_uri' => $secret->toUri(),
            'qr_svg' => $secret->toQr(),
        ]);
    }

    public function confirm(TwoFactorConfirmRequest $request): JsonResponse
    {
        if (config('yaffa.sandbox_mode')) {
            return response()->json([
                'message' => __('This action is not allowed in sandbox mode.'),
            ], 403);
        }

        /** @var User $user */
        $user = $request->user();

        if (! $user->confirmTwoFactorAuth($request->validated('code'))) {
            return response()->json([
                'message' => __('The provided code is invalid.'),
            ], 422);
        }

        return response()->json([
            'enabled' => true,
            'recovery_codes' => $user->getRecoveryCodes()->pluck('code')->values(),
        ]);
    }

    public function disable(TwoFactorPasswordRequest $request): JsonResponse
    {
        if (config('yaffa.sandbox_mode')) {
            return response()->json([
                'message' => __('This action is not allowed in sandbox mode.'),
            ], 403);
        }

        /** @var User $user */
        $user = $request->user();

        $user->disableTwoFactorAuth();

        return response()->json([
            'enabled' => false,
        ]);
    }

    public function regenerateRecoveryCodes(TwoFactorPasswordRequest $request): JsonResponse
    {
        if (config('yaffa.sandbox_mode')) {
            return response()->json([
                'message' => __('This action is not allowed in sandbox mode.'),
            ], 403);
        }

        /** @var User $user */
        $user = $request->user();

        if (! $user->hasTwoFactorEnabled()) {
            return response()->json([
                'message' => __('Two-factor authentication is not enabled.'),
            ], 422);
        }

        return response()->json([
            'recovery_codes' => $user->generateRecoveryCodes()->pluck('code')->values(),
        ]);
    }
}
