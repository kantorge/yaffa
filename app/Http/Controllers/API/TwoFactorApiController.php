<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\TwoFactorConfirmRequest;
use App\Http\Requests\TwoFactorPasswordRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Middleware;

#[Middleware('auth:sanctum')]
#[Middleware('verified')]
#[Middleware('abilities:settings', only: [
    'enroll', 'confirm', 'disable', 'regenerateRecoveryCodes',
])]
class TwoFactorApiController extends Controller
{
    /**
     * Get two-factor status
     */
    public function show(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'enabled' => $user->hasTwoFactorEnabled(),
        ]);
    }

    /**
     * Start two-factor enrollment
     *
     * Generates a new TOTP secret and QR code. This is always a "start fresh" operation;
     * disable two-factor first if it is already enabled.
     */
    public function enroll(Request $request): JsonResponse
    {
        if (config('yaffa.sandbox_mode')) {
            return response()->json([
                'message' => __('This action is not allowed in sandbox mode.'),
            ], 403);
        }

        /** @var User $user */
        $user = $request->user();

        // createTwoFactorAuth() unconditionally flushes any existing secret and clears
        // enabled_at (laragear/two-factor's flushAuth()), so re-enrolling an already-
        // confirmed account would silently disable the user's real 2FA. Enrollment is
        // only ever a "start fresh" operation; disable first if 2FA is already on.
        if ($user->hasTwoFactorEnabled()) {
            return response()->json([
                'message' => __('Two-factor authentication is already enabled. Disable it before enrolling again.'),
            ], 422);
        }

        $secret = $user->createTwoFactorAuth();

        return response()->json([
            'secret' => mb_trim(chunk_split($secret->toString(), 4, ' ')),
            'otpauth_uri' => $secret->toUri(),
            'qr_svg' => $secret->toQr(),
        ]);
    }

    /**
     * Confirm two-factor enrollment
     *
     * Verifies the submitted code against the pending secret, enables two-factor
     * authentication, and returns the recovery codes.
     */
    public function confirm(TwoFactorConfirmRequest $request): JsonResponse
    {
        if (config('yaffa.sandbox_mode')) {
            return response()->json([
                'message' => __('This action is not allowed in sandbox mode.'),
            ], 403);
        }

        /** @var User $user */
        $user = $request->user();

        // confirmTwoFactorAuth() short-circuits to true (without checking the code at
        // all) when 2FA is already enabled, so without this guard any caller could POST
        // an arbitrary code here and receive the account's live recovery codes back.
        if ($user->hasTwoFactorEnabled()) {
            return response()->json([
                'message' => __('Two-factor authentication is already enabled.'),
            ], 422);
        }

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

    /**
     * Disable two-factor authentication
     */
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

    /**
     * Regenerate recovery codes
     */
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
