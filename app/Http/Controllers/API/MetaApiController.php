<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\UploadLimitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MetaApiController extends Controller
{
    /**
     * Get server information
     *
     * Public: reports the API version, the token header name and feature flags, so a client can check
     * compatibility before it holds a token. When a valid token is sent, a `user` context is added.
     */
    public function show(Request $request): JsonResponse
    {
        $data = [
            'api_version' => 1,
            'min_app_version' => config('yaffa.mobile.min_app_version'),
            'token_header' => 'X-Yaffa-Token',
            'features' => [
                'ai_documents' => true,
                'notifications' => true,
                'push' => false,
            ],
        ];

        /** @var User|null $user */
        $user = $request->user('sanctum');

        if ($user !== null) {
            $baseCurrency = $user->baseCurrency();

            // The exact version is only shown to authenticated callers, to limit fingerprinting.
            $data['yaffa_version'] = config('yaffa.version');
            $data['idempotency_retention_days'] = (int) config('yaffa.idempotency_retention_days');
            $data['user'] = [
                'base_currency' => $baseCurrency ? [
                    'id' => $baseCurrency->id,
                    'iso_code' => $baseCurrency->iso_code,
                ] : null,
                'locale' => $user->locale,
                'max_upload_mb' => UploadLimitService::maxFileMb(),
                'max_files_per_submission' => (int) config('ai-documents.file_upload.max_files_per_submission'),
            ];
        }

        return response()->json($data);
    }
}
