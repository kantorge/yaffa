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
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Routing\Attributes\Controllers\Middleware;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

#[Middleware('auth:sanctum')]
#[Middleware('verified')]
#[Middleware('abilities:settings', only: [
                'show', 'store', 'update', 'destroy', 'test',
                'sync', 'folderName', 'folderNameByCredentials',
                'folders', 'foldersByCredentials',
            ])]
class GoogleDriveConfigApiController extends Controller
{
    /**
     * Required Google service account JSON keys.
     *
     * @var array<int, string>
     */
    private const REQUIRED_SERVICE_ACCOUNT_KEYS = [
        'type',
        'project_id',
        'private_key_id',
        'private_key',
        'client_email',
        'client_id',
        'auth_uri',
        'token_uri',
    ];

    public function __construct(
        private GoogleDriveService $googleDriveService
    ) {}

    /**
     * Get Google Drive configuration
     *
     * Returns the current Google Drive configuration for the authenticated user.
     */
    public function show(Request $request): JsonResponse
    {
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
     * Create Google Drive configuration
     *
     * Creates the Google Drive configuration for the authenticated user, replacing any
     * existing configuration (only one config is supported per user).
     *
     * @throws AuthorizationException
     */
    #[Authorize('create', GoogleDriveConfig::class)]
    public function store(GoogleDriveConfigRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        // Delete existing config if present (enforce one per user for MVP)
        $user->googleDriveConfigs()->delete();

        // Extract service account email from JSON
        $credentials = json_decode($request->input('service_account_json'), true);
        $serviceAccountEmail = $credentials['client_email'] ?? null;

        // Create new config
        $config = GoogleDriveConfig::create([
            'service_account_email' => $serviceAccountEmail,
            'service_account_json' => $request->input('service_account_json'),
            'folder_id' => $request->input('folder_id'),
            'folder_name' => $request->input('folder_name'),
            'post_import_actions' => $request->input('post_import_actions'),
            'processed_folder_id' => $request->input('processed_folder_id'),
            'processed_folder_name' => $request->input('processed_folder_name'),
            'sync_interval_minutes' => $request->input('sync_interval_minutes', 15),
            'enabled' => $request->input('enabled', true),
        ]);

        return response()->json((new GoogleDriveConfigResource($config))->resolve(), Response::HTTP_CREATED);
    }

    /**
     * Update Google Drive configuration
     *
     * @throws AuthorizationException
     */
    #[Authorize('update', 'googleDriveConfig')]
    public function update(GoogleDriveConfigRequest $request, GoogleDriveConfig $googleDriveConfig): JsonResponse
    {
        $validated = $request->validated();

        // Prepare update data
        $updateData = [
            'folder_id' => $validated['folder_id'] ?? $googleDriveConfig->folder_id,
            'folder_name' => array_key_exists('folder_name', $validated) ? $validated['folder_name'] : $googleDriveConfig->folder_name,
            'post_import_actions' => array_key_exists('post_import_actions', $validated) ? $validated['post_import_actions'] : $googleDriveConfig->post_import_actions,
            'processed_folder_id' => array_key_exists('processed_folder_id', $validated) ? $validated['processed_folder_id'] : $googleDriveConfig->processed_folder_id,
            'processed_folder_name' => array_key_exists('processed_folder_name', $validated) ? $validated['processed_folder_name'] : $googleDriveConfig->processed_folder_name,
            'sync_interval_minutes' => $validated['sync_interval_minutes'] ?? $googleDriveConfig->sync_interval_minutes,
            'enabled' => array_key_exists('enabled', $validated) ? $validated['enabled'] : $googleDriveConfig->enabled,
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
     * Delete Google Drive configuration
     *
     * @throws AuthorizationException
     */
    #[Authorize('delete', 'googleDriveConfig')]
    public function destroy(GoogleDriveConfig $googleDriveConfig): JsonResponse
    {
        $googleDriveConfig->delete();

        return response()->json([], Response::HTTP_NO_CONTENT);
    }

    /**
     * Test Google Drive connection
     *
     * Validates the provided credentials by attempting to connect to the configured folder(s).
     */
    public function test(GoogleDriveConfigRequest $request): JsonResponse
    {
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
            $result = $this->googleDriveService->testConnection(
                $credentials,
                $validated['folder_id'],
                $validated['processed_folder_id'] ?? null
            );

            if ($result['success']) {
                return response()->json([
                    'message' => $result['message'],
                    'file_count' => $result['file_count'],
                    'folder_name' => $result['folder_name'] ?? null,
                    'test_file_found' => $result['test_file_found'] ?? false,
                    'capabilities_source' => $result['capabilities_source'] ?? null,
                    'capabilities' => $result['capabilities'] ?? null,
                    'recommended_actions' => $result['recommended_actions'] ?? [],
                    'notice' => $result['notice'] ?? null,
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
     * Trigger Google Drive sync
     *
     * Manually queues a sync job for the given configuration.
     *
     * @throws AuthorizationException
     */
    #[Authorize('sync', 'googleDriveConfig')]
    public function sync(GoogleDriveConfig $googleDriveConfig): JsonResponse
    {
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

    /**
     * Get Google Drive folder name
     *
     * Fetch the display name of the config's import folder (or a different folder via ?folder_id=).
     *
     * @throws AuthorizationException
     */
    #[Authorize('view', 'googleDriveConfig')]
    public function folderName(Request $request, GoogleDriveConfig $googleDriveConfig): JsonResponse
    {
        $folderId = $request->query('folder_id', $googleDriveConfig->folder_id);

        if (empty($folderId)) {
            return response()->json([
                'error' => [
                    'code' => 'MISSING_FOLDER_ID',
                    'message' => __('No folder ID provided.'),
                ],
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $credentials = json_decode($googleDriveConfig->service_account_json, true);
            $folderName = $this->googleDriveService->getFolderName($folderId, $credentials);

            return response()->json(['folder_name' => $folderName], Response::HTTP_OK);
        } catch (Exception $e) {
            Log::warning('Could not fetch Google Drive folder name', [
                'config_id' => $googleDriveConfig->id,
                'folder_id' => $folderId,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['folder_name' => null], Response::HTTP_OK);
        }
    }

    /**
     * Get folder name by credentials
     *
     * Fetch the display name for a folder using provided credentials, without requiring
     * a saved configuration.
     *
     * @throws AuthorizationException
     */
    #[Authorize('create', GoogleDriveConfig::class)]
    public function folderNameByCredentials(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'folder_id' => ['required', 'string', 'max:255'],
            'service_account_json' => [
                'required',
                'string',
                function ($attribute, $value, $fail) {
                    $this->validateServiceAccountPayload($attribute, $value, $fail);
                },
            ],
        ]);

        $resolvedCredentials = $this->resolveRequestCredentials($request, $validated['service_account_json']);

        if ($resolvedCredentials instanceof JsonResponse) {
            return $resolvedCredentials;
        }

        try {
            $folderName = $this->googleDriveService->getFolderName($validated['folder_id'], $resolvedCredentials);

            return response()->json(['folder_name' => $folderName], Response::HTTP_OK);
        } catch (Exception $e) {
            Log::warning('Could not fetch Google Drive folder name', [
                'folder_id' => $validated['folder_id'],
                'error' => $e->getMessage(),
            ]);

            return response()->json(['folder_name' => null], Response::HTTP_OK);
        }
    }

    /**
     * List Google Drive folders
     *
     * List Drive folders accessible to the service account, optionally under a given parent.
     *
     * @throws AuthorizationException
     */
    #[Authorize('view', 'googleDriveConfig')]
    public function folders(Request $request, GoogleDriveConfig $googleDriveConfig): JsonResponse
    {
        $parentId = $request->query('parent_id');

        try {
            $folderListing = $this->googleDriveService->listFolders($googleDriveConfig, $parentId ?: null);
            $folders = $folderListing['folders'];
            $foldersTruncated = $folderListing['truncated'];

            $response = [
                'folders' => $folders,
                'folders_truncated' => $foldersTruncated,
            ];

            if ($foldersTruncated) {
                $response['notice'] = __('Folder list is truncated to the first page of Google Drive results. Open a parent folder to narrow results.');
            }

            return response()->json($response, Response::HTTP_OK);
        } catch (\Google\Service\Exception $e) {
            if (in_array($e->getCode(), [401, 403], true)) {
                return response()->json([
                    'error' => [
                        'code' => 'PERMISSION_DENIED',
                        'message' => __('Could not access Google Drive. Ensure the service account credentials are valid.'),
                    ],
                ], Response::HTTP_FORBIDDEN);
            }

            Log::error('Google Drive folder listing failed', [
                'config_id' => $googleDriveConfig->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => [
                    'code' => 'DRIVE_ERROR',
                    'message' => __('Failed to list folders: :error', ['error' => $e->getMessage()]),
                ],
            ], Response::HTTP_BAD_GATEWAY);
        } catch (Exception $e) {
            Log::error('Google Drive folder listing failed', [
                'config_id' => $googleDriveConfig->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => [
                    'code' => 'UNKNOWN_ERROR',
                    'message' => __('Failed to list folders: :error', ['error' => $e->getMessage()]),
                ],
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * List folders by credentials
     *
     * List Drive folders accessible using provided credentials, optionally under a given parent.
     *
     * @throws AuthorizationException
     */
    #[Authorize('create', GoogleDriveConfig::class)]
    public function foldersByCredentials(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'parent_id' => ['nullable', 'string', 'max:255'],
            'service_account_json' => [
                'required',
                'string',
                function ($attribute, $value, $fail) {
                    $this->validateServiceAccountPayload($attribute, $value, $fail);
                },
            ],
        ]);

        $resolvedCredentials = $this->resolveRequestCredentials($request, $validated['service_account_json']);

        if ($resolvedCredentials instanceof JsonResponse) {
            return $resolvedCredentials;
        }

        $parentId = $validated['parent_id'] ?? null;

        try {
            $folderListing = $this->googleDriveService->listFoldersByCredentials($resolvedCredentials, $parentId ?: null);
            $folders = $folderListing['folders'];
            $foldersTruncated = $folderListing['truncated'];

            $response = [
                'folders' => $folders,
                'folders_truncated' => $foldersTruncated,
            ];

            if ($foldersTruncated) {
                $response['notice'] = __('Folder list is truncated to the first page of Google Drive results. Open a parent folder to narrow results.');
            }

            return response()->json($response, Response::HTTP_OK);
        } catch (\Google\Service\Exception $e) {
            if (in_array($e->getCode(), [401, 403], true)) {
                return response()->json([
                    'error' => [
                        'code' => 'PERMISSION_DENIED',
                        'message' => __('Could not access Google Drive. Ensure the service account credentials are valid.'),
                    ],
                ], Response::HTTP_FORBIDDEN);
            }

            Log::error('Google Drive folder listing failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => [
                    'code' => 'DRIVE_ERROR',
                    'message' => __('Failed to list folders: :error', ['error' => $e->getMessage()]),
                ],
            ], Response::HTTP_BAD_GATEWAY);
        } catch (Exception $e) {
            Log::error('Google Drive folder listing failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => [
                    'code' => 'UNKNOWN_ERROR',
                    'message' => __('Failed to list folders: :error', ['error' => $e->getMessage()]),
                ],
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    private function resolveRequestCredentials(Request $request, string $serviceAccountJson): array|JsonResponse
    {
        if ($serviceAccountJson === '__existing__') {
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

            return json_decode($existingConfig->service_account_json, true);
        }

        return json_decode($serviceAccountJson, true);
    }

    private function validateServiceAccountPayload(string $attribute, mixed $value, callable $fail): void
    {
        if ($value === '__existing__') {
            return;
        }

        $decoded = json_decode($value, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $fail(__('The :attribute must be valid JSON.', ['attribute' => $attribute]));

            return;
        }

        foreach (self::REQUIRED_SERVICE_ACCOUNT_KEYS as $key) {
            if (! isset($decoded[$key]) || empty($decoded[$key])) {
                $fail(__('The :attribute is missing required key: :key', [
                    'attribute' => $attribute,
                    'key' => $key,
                ]));

                return;
            }
        }

        if (($decoded['type'] ?? null) !== 'service_account') {
            $fail(__('The :attribute must be a service account JSON key file.', ['attribute' => $attribute]));

            return;
        }

        // Reject service accounts whose auth endpoints aren't Google's own, which would
        // otherwise let the server be tricked into sending authenticated requests elsewhere (SSRF)
        try {
            GoogleDriveService::assertTrustedAuthEndpoints($decoded);
        } catch (InvalidArgumentException $exception) {
            $fail($exception->getMessage());
        }
    }
}
