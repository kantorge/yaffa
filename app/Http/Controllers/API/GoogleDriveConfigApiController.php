<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\GoogleDriveConfigRequest;
use App\Http\Resources\GoogleDriveConfigResource;
use App\Jobs\ProcessGoogleDriveConfigJob;
use App\Models\GoogleDriveConfig;
use App\Models\User;
use App\Services\GoogleDriveService;
use Exception;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class GoogleDriveConfigApiController extends Controller implements HasMiddleware
{
    private const string FEATURE_DISABLED_MESSAGE = 'Google Drive integration is disabled in configuration';

    public function __construct(
        private GoogleDriveService $googleDriveService
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
        /**
         * @get("/api/v1/google-drive/config")
         * @name("api.v1.google-drive.config.show")
         * @middlewares("api", "auth:sanctum", "verified")
         */

        if ($response = $this->ensureGoogleDriveFeatureEnabled()) {
            return $response;
        }

        // For MVP, we assume one config per user
        /** @var User $user */
        $user = $request->user();
        $config = $user->googleDriveConfigs()->first();

        if (! $config) {
            return response()->json([
                'error' => [
                    'code' => 'NOT_FOUND',
                    'message' => __('No Google Drive configuration found'),
                ],
            ], Response::HTTP_NOT_FOUND);
        }

        Gate::authorize('view', $config);

        return response()->json((new GoogleDriveConfigResource($config))->resolve(), Response::HTTP_OK);
    }

    /**
     * @throws AuthorizationException
     */
    public function store(GoogleDriveConfigRequest $request): JsonResponse
    {
        /**
         * @post("/api/v1/google-drive/config")
         * @name("api.v1.google-drive.config.store")
         * @middlewares("api", "auth:sanctum", "verified")
         */

        if ($response = $this->ensureGoogleDriveFeatureEnabled()) {
            return $response;
        }

        Gate::authorize('create', GoogleDriveConfig::class);

        /** @var User $user */
        $user = $request->user();

        // Delete existing config if present (enforce one per user for MVP)
        $user->googleDriveConfigs()->delete();

        // Extract service account email from JSON
        $credentials = json_decode($request->input('service_account_json'), true);
        $serviceAccountEmail = $credentials['client_email'] ?? null;

        // Create new config
        $config = GoogleDriveConfig::create([
            'user_id' => $user->id,
            'service_account_email' => $serviceAccountEmail,
            'service_account_json' => $request->input('service_account_json'),
            'folder_id' => $request->input('folder_id'),
            'delete_after_import' => $request->input('delete_after_import', false),
            'enabled' => $request->input('enabled', true),
        ]);

        return response()->json([
            new GoogleDriveConfigResource($config)->resolve()
        ], Response::HTTP_CREATED);
    }

    /**
     * PATCH /api/v1/google-drive/config/{id} - Update Google Drive config
     *
     * @throws AuthorizationException
     */
    public function update(GoogleDriveConfigRequest $request, GoogleDriveConfig $googleDriveConfig): JsonResponse
    {
        if ($response = $this->ensureGoogleDriveFeatureEnabled()) {
            return $response;
        }

        Gate::authorize('update', $googleDriveConfig);

        $validated = $request->validated();

        // Prepare update data
        $updateData = [
            'folder_id' => $validated['folder_id'] ?? $googleDriveConfig->folder_id,
            'delete_after_import' => $validated['delete_after_import'] ?? false,
            'enabled' => $validated['enabled'] ?? true,
        ];

        // Only update service account JSON if provided and not the placeholder
        if (! empty($validated['service_account_json']) && $validated['service_account_json'] !== '__existing__') {
            $updateData['service_account_json'] = $validated['service_account_json'];

            // Extract and update service account email from JSON
            $credentials = json_decode($validated['service_account_json'], true);
            $updateData['service_account_email'] = $credentials['client_email'] ?? null;
        }

        $googleDriveConfig->update($updateData);

        return response()->json((new GoogleDriveConfigResource($googleDriveConfig))->resolve(), Response::HTTP_OK);
    }

    /**
     * DELETE /api/v1/google-drive/config/{id} - Delete Google Drive config
     *
     * @throws AuthorizationException
     */
    public function destroy(GoogleDriveConfig $googleDriveConfig): JsonResponse
    {
        if ($response = $this->ensureGoogleDriveFeatureEnabled()) {
            return $response;
        }

        Gate::authorize('delete', $googleDriveConfig);

        $googleDriveConfig->delete();

        return response()->json([], Response::HTTP_NO_CONTENT);
    }

    /**
     * POST /api/v1/google-drive/test - Test Google Drive connection
     */
    public function test(GoogleDriveConfigRequest $request): JsonResponse
    {
        if ($response = $this->ensureGoogleDriveFeatureEnabled()) {
            return $response;
        }

        // Basic validation is already done by GoogleDriveConfigRequest
        $validated = $request->validated();

        // If the service account JSON is indicated as existing, fetch the stored JSON
        if (($validated['service_account_json'] ?? null) === '__existing__') {
            /** @var User $user */
            $user = $request->user();
            $existingConfig = $user->googleDriveConfigs()->first();
            if (! $existingConfig) {
                return response()->json([
                    'error' => [
                        'code' => 'CONFIG_NOT_FOUND',
                        'message' => __('No existing Google Drive configuration found'),
                    ],
                ], Response::HTTP_BAD_REQUEST);
            }
            $validated['service_account_json'] = $existingConfig->service_account_json;
        }

        try {
            // Decode the service account JSON
            $credentials = json_decode($validated['service_account_json'], true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return response()->json([
                    'error' => [
                        'code' => 'VALIDATION_ERROR',
                        'message' => __('Invalid JSON format in service account credentials'),
                    ],
                ], Response::HTTP_BAD_REQUEST);
            }

            // Test the connection using the GoogleDriveService
            $result = $this->googleDriveService->testConnection($credentials, $validated['folder_id']);

            if ($result['success']) {
                return response()->json([
                    'message' => $result['message'],
                    'file_count' => $result['file_count'],
                    'has_delete_permission' => $result['has_delete_permission'],
                ], Response::HTTP_OK);
            }

            return response()->json([
                'error' => [
                    'code' => 'CONNECTION_FAILED',
                    'message' => $result['message'],
                ],
            ], Response::HTTP_BAD_REQUEST);
        } catch (Exception $e) {
            Log::error("Google Drive test failed: {$e->getMessage()}");

            return response()->json([
                'error' => [
                    'code' => 'CONNECTION_FAILED',
                    'message' => __('Connection failed: :error', ['error' => $e->getMessage()]),
                ],
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * POST /api/v1/google-drive/sync/{id} - Manually trigger sync for a config
     *
     * @throws AuthorizationException
     */
    public function sync(GoogleDriveConfig $googleDriveConfig): JsonResponse
    {
        if ($response = $this->ensureGoogleDriveFeatureEnabled()) {
            return $response;
        }

        Gate::authorize('sync', $googleDriveConfig);

        if (! $googleDriveConfig->enabled) {
            return response()->json([
                'error' => [
                    'code' => 'CONFIG_DISABLED',
                    'message' => __('Cannot sync disabled Google Drive configuration'),
                ],
            ], Response::HTTP_BAD_REQUEST);
        }

        // Dispatch the config-specific job to process this config
        ProcessGoogleDriveConfigJob::dispatch($googleDriveConfig->id, true);

        return response()->json([
            'message' => __('Google Drive sync has been queued'),
        ], Response::HTTP_ACCEPTED);
    }

    private function ensureGoogleDriveFeatureEnabled(): ?JsonResponse
    {
        if (config('ai-documents.google_drive.enabled')) {
            return null;
        }

        return response()->json([
            'error' => [
                'code' => 'FEATURE_DISABLED',
                'message' => __(self::FEATURE_DISABLED_MESSAGE),
            ],
        ], Response::HTTP_FORBIDDEN);
    }
}
