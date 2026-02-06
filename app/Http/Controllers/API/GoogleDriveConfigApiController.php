<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\GoogleDriveConfigRequest;
use App\Models\GoogleDriveConfig;
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
    public function __construct(
        private GoogleDriveService $googleDriveService
    ) {
    }

    public static function middleware(): array
    {
        return [
            ['auth:sanctum', 'verified'],
        ];
    }

    /**
     * GET /api/google-drive/config - Get user's Google Drive config
     */
    public function show(Request $request): JsonResponse
    {
        // For MVP, we assume one config per user
        $user = $request->user();
        $config = $user->googleDriveConfigs()->first();

        if (! $config) {
            return response()->json([
                'error' => __('No Google Drive configuration found'),
            ], Response::HTTP_NOT_FOUND);
        }

        Gate::authorize('view', $config);

        // Return config without exposing service account JSON
        return response()->json([
            'id' => $config->id,
            'service_account_email' => $config->service_account_email,
            'folder_id' => $config->folder_id,
            'delete_after_import' => $config->delete_after_import,
            'enabled' => $config->enabled,
            'last_sync_at' => $config->last_sync_at,
            'last_error' => $config->last_error,
            'error_count' => $config->error_count,
            'created_at' => $config->created_at,
            'updated_at' => $config->updated_at,
        ], Response::HTTP_OK);
    }

    /**
     * POST /api/google-drive/config - Create Google Drive config
     *
     * @throws AuthorizationException
     */
    public function store(GoogleDriveConfigRequest $request): JsonResponse
    {
        Gate::authorize('create', GoogleDriveConfig::class);

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
            'id' => $config->id,
            'service_account_email' => $config->service_account_email,
            'folder_id' => $config->folder_id,
            'delete_after_import' => $config->delete_after_import,
            'enabled' => $config->enabled,
            'message' => __('Google Drive configured successfully'),
        ], Response::HTTP_CREATED);
    }

    /**
     * PATCH /api/google-drive/config/{id} - Update Google Drive config
     *
     * @throws AuthorizationException
     */
    public function update(GoogleDriveConfigRequest $request, GoogleDriveConfig $googleDriveConfig): JsonResponse
    {
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

        return response()->json([
            'id' => $googleDriveConfig->id,
            'service_account_email' => $googleDriveConfig->service_account_email,
            'folder_id' => $googleDriveConfig->folder_id,
            'delete_after_import' => $googleDriveConfig->delete_after_import,
            'enabled' => $googleDriveConfig->enabled,
            'updated_at' => $googleDriveConfig->updated_at,
        ], Response::HTTP_OK);
    }

    /**
     * DELETE /api/google-drive/config/{id} - Delete Google Drive config
     *
     * @throws AuthorizationException
     */
    public function destroy(GoogleDriveConfig $googleDriveConfig): JsonResponse
    {
        Gate::authorize('delete', $googleDriveConfig);

        $googleDriveConfig->delete();

        return response()->json([], Response::HTTP_NO_CONTENT);
    }

    /**
     * POST /api/google-drive/test - Test Google Drive connection
     */
    public function test(GoogleDriveConfigRequest $request): JsonResponse
    {
        // Basic validation is already done by GoogleDriveConfigRequest
        $validated = $request->validated();

        // If the service account JSON is indicated as existing, fetch the stored JSON
        if (($validated['service_account_json'] ?? null) === '__existing__') {
            $user = $request->user();
            $existingConfig = $user->googleDriveConfigs()->first();
            if (! $existingConfig) {
                return response()->json([
                    'message' => __('No existing Google Drive configuration found'),
                ], Response::HTTP_BAD_REQUEST);
            }
            $validated['service_account_json'] = $existingConfig->service_account_json;
        }

        try {
            // Decode the service account JSON
            $credentials = json_decode($validated['service_account_json'], true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return response()->json([
                    'message' => __('Invalid JSON format in service account credentials'),
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
                'message' => $result['message'],
            ], Response::HTTP_BAD_REQUEST);
        } catch (Exception $e) {
            Log::error("Google Drive test failed: {$e->getMessage()}");

            return response()->json([
                'message' => __('Connection failed: :error', ['error' => $e->getMessage()]),
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * POST /api/google-drive/sync/{id} - Manually trigger sync for a config
     *
     * @throws AuthorizationException
     */
    public function sync(GoogleDriveConfig $googleDriveConfig): JsonResponse
    {
        Gate::authorize('sync', $googleDriveConfig);

        // Note: The actual sync job is not implemented yet
        // This endpoint is just a placeholder for future implementation
        return response()->json([
            'message' => __('Sync job not implemented yet'),
        ], Response::HTTP_OK);
    }
}
