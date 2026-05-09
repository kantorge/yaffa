<?php

namespace App\Services;

use App\Models\GoogleDriveConfig;
use Exception;
use Google\Client;
use Google\Service\Drive;
use Google\Service\Drive\DriveFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class GoogleDriveService
{
    /**
     * File names that are always excluded from normal import scans.
     */
    private const EXCLUDED_FILE_NAME = 'yaffa.txt';

    /**
     * Prefix applied by the rename_processed disposition action.
     */
    private const PROCESSED_PREFIX = 'processed_';

    /**
     * Valid disposition action keys in fixed priority order.
     *
     * @var array<int, string>
     */
    public const DISPOSITION_ACTIONS = ['delete', 'trash', 'move_to_processed', 'rename_processed'];

    /**
     * List files in the Google Drive folder, by default since last_sync_at.
     * Files whose name starts with "processed_" or equals "yaffa.txt" are silently excluded.
     *
     * @param GoogleDriveConfig $config
     * @param bool $sinceLastSync
     * @return array<int, array{id: string, name: string, mimeType: string, modifiedTime: string}>
     */
    public function listNewFiles(GoogleDriveConfig $config, bool $sinceLastSync = true): array
    {
        $credentials = json_decode($config->service_account_json, true);
        $client = $this->createClient($credentials);
        $service = new Drive($client);
        $folderId = $config->folder_id;
        $q = "'{$folderId}' in parents and trashed=false";

        if ($sinceLastSync && $config->last_sync_at) {
            $q .= " and modifiedTime > '" . $config->last_sync_at->toRfc3339String() . "'";
        }

        $files = [];
        $pageToken = null;

        do {
            $params = [
                'q' => $q,
                'fields' => 'nextPageToken, files(id, name, mimeType, modifiedTime)',
                'pageSize' => 50,
                'includeItemsFromAllDrives' => true,
                'supportsAllDrives' => true,
            ];

            if ($pageToken) {
                $params['pageToken'] = $pageToken;
            }

            $response = $service->files->listFiles($params);

            foreach ($response->getFiles() as $file) {
                $name = $file->getName();

                if ($this->isExcludedFromImport($name)) {
                    continue;
                }

                $files[] = [
                    'id' => $file->getId(),
                    'name' => $name,
                    'mimeType' => $file->getMimeType(),
                    'modifiedTime' => $file->getModifiedTime(),
                ];
            }

            $pageToken = $response->getNextPageToken();
        } while ($pageToken);

        return $files;
    }

    /**
     * Download a file from Google Drive to the given destination path.
     */
    public function downloadFile(string $fileId, array $credentials, string $destination): void
    {
        $client = $this->createClient($credentials);
        $service = new Drive($client);
        $content = $service->files->get($fileId, [
            'alt' => 'media',
            'supportsAllDrives' => true,
        ]);
        file_put_contents($destination, $content->getBody()->getContents());
    }

    /**
     * Permanently delete a file from Google Drive.
     * Returns true on success or if the file is already gone, false on permission failure.
     *
     * @throws \Google\Service\Exception On unexpected API errors.
     */
    public function deleteFile(GoogleDriveConfig $config, string $fileId): bool
    {
        $service = new Drive($this->createClient(json_decode($config->service_account_json, true)));

        try {
            $service->files->delete($fileId, ['supportsAllDrives' => true]);

            return true;
        } catch (\Google\Service\Exception $e) {
            if ($e->getCode() === 404) {
                return true;
            }

            if (in_array($e->getCode(), [401, 403], true)) {
                return false;
            }

            throw $e;
        }
    }

    /**
     * Move a file to the owner's trash.
     * Returns true on success or if the file is already gone, false on permission failure.
     *
     * @throws \Google\Service\Exception On unexpected API errors.
     */
    public function trashFile(GoogleDriveConfig $config, string $fileId): bool
    {
        $service = new Drive($this->createClient(json_decode($config->service_account_json, true)));

        try {
            $service->files->update(
                $fileId,
                new DriveFile(['trashed' => true]),
                ['supportsAllDrives' => true]
            );

            return true;
        } catch (\Google\Service\Exception $e) {
            if ($e->getCode() === 404) {
                return true;
            }

            if (in_array($e->getCode(), [401, 403], true)) {
                return false;
            }

            throw $e;
        }
    }

    /**
     * Move a file to a different folder by updating its parents.
     * Returns true on success or if the file is already gone, false on permission failure.
     *
     * @throws \Google\Service\Exception On unexpected API errors.
     */
    public function moveFile(GoogleDriveConfig $config, string $fileId, string $targetFolderId, string $currentParentId): bool
    {
        $service = new Drive($this->createClient(json_decode($config->service_account_json, true)));

        try {
            $service->files->update(
                $fileId,
                new DriveFile(),
                [
                    'addParents' => $targetFolderId,
                    'removeParents' => $currentParentId,
                    'supportsAllDrives' => true,
                ]
            );

            return true;
        } catch (\Google\Service\Exception $e) {
            if ($e->getCode() === 404) {
                return true;
            }

            if (in_array($e->getCode(), [401, 403], true)) {
                return false;
            }

            throw $e;
        }
    }

    /**
     * Rename a file in Google Drive.
     * Returns true on success or if the file is already gone, false on permission failure.
     *
     * @throws \Google\Service\Exception On unexpected API errors.
     */
    public function renameFile(GoogleDriveConfig $config, string $fileId, string $newName): bool
    {
        $service = new Drive($this->createClient(json_decode($config->service_account_json, true)));

        try {
            $service->files->update(
                $fileId,
                new DriveFile(['name' => $newName]),
                ['supportsAllDrives' => true]
            );

            return true;
        } catch (\Google\Service\Exception $e) {
            if ($e->getCode() === 404) {
                return true;
            }

            if (in_array($e->getCode(), [401, 403], true)) {
                return false;
            }

            throw $e;
        }
    }

    /**
     * Try each enabled disposition action in fixed priority order and stop at the first success.
     * Does not throw — all errors are captured into the returned result.
     */
    public function attemptDisposition(
        GoogleDriveConfig $config,
        string $fileId,
        string $originalName,
        string $currentParentId
    ): DispositionResult {
        $actions = $config->post_import_actions ?? [];

        if (empty($actions)) {
            return new DispositionResult(false, null, []);
        }

        $failureReasons = [];

        foreach ($actions as $action) {
            if (! in_array($action, self::DISPOSITION_ACTIONS, true)) {
                Log::warning('Skipping unknown Google Drive disposition action', ['action' => $action]);
                continue;
            }

            try {
                $success = match ($action) {
                    'delete' => $this->deleteFile($config, $fileId),
                    'trash' => $this->trashFile($config, $fileId),
                    'move_to_processed' => $config->processed_folder_id
                        ? $this->moveFile($config, $fileId, $config->processed_folder_id, $currentParentId)
                        : false,
                    'rename_processed' => $this->renameFile(
                        $config,
                        $fileId,
                        Str::startsWith($originalName, self::PROCESSED_PREFIX)
                            ? $originalName
                            : self::PROCESSED_PREFIX . $originalName
                    ),
                };

                if ($success) {
                    return new DispositionResult(true, $action, $failureReasons);
                }

                $failureReasons[$action] = 'Permission denied';
            } catch (Exception $e) {
                Log::warning('Google Drive disposition action failed', [
                    'action' => $action,
                    'file_id' => $fileId,
                    'error' => $e->getMessage(),
                ]);
                $failureReasons[$action] = $e->getMessage();
            }
        }

        return new DispositionResult(false, null, $failureReasons);
    }

    /**
     * Retrieve the display name of a Google Drive folder.
     *
     * @throws Exception When the folder cannot be accessed.
     */
    public function getFolderName(string $folderId, array $credentials): string
    {
        $service = new Drive($this->createClient($credentials));
        $folder = $service->files->get($folderId, [
            'fields' => 'name',
            'supportsAllDrives' => true,
        ]);

        return (string) $folder->getName();
    }

    /**
     * List Drive folders accessible to the service account under the given parent.
     * Queries both root-parented folders and folders shared with the service account.
     *
     * @return array<int, array{id: string, name: string}>
     */
    public function listFolders(GoogleDriveConfig $config, ?string $parentId = null): array
    {
        $credentials = json_decode($config->service_account_json, true);
        $service = new Drive($this->createClient($credentials));

        $mimeTypeFilter = "mimeType = 'application/vnd.google-apps.folder'";
        $queries = [];

        if ($parentId) {
            $queries[] = "'{$parentId}' in parents and {$mimeTypeFilter} and trashed=false";
        } else {
            $queries[] = "'root' in parents and {$mimeTypeFilter} and trashed=false";
            $queries[] = "sharedWithMe = true and {$mimeTypeFilter} and trashed=false";
        }

        $folders = [];

        foreach ($queries as $q) {
            $response = $service->files->listFiles([
                'q' => $q,
                'fields' => 'files(id, name)',
                'pageSize' => 100,
                'includeItemsFromAllDrives' => true,
                'supportsAllDrives' => true,
                'orderBy' => 'name',
            ]);

            foreach ($response->getFiles() as $folder) {
                $folders[] = [
                    'id' => $folder->getId(),
                    'name' => $folder->getName(),
                ];
            }
        }

        return $folders;
    }

    /**
     * Test connection to Google Drive. Returns folder accessibility, file count, folder name,
     * and per-action capability results based on a real test file or estimated via a temp file.
     *
     * @param array $credentials Decoded service account JSON
     * @param string $folderId Google Drive folder ID
     * @param string|null $processedFolderId Optional processed folder ID (for move_to_processed capability)
     * @return array{
     *     success: bool,
     *     message: string,
     *     file_count?: int,
     *     folder_name?: string|null,
     *     test_file_found?: bool,
     *     capabilities_source?: string,
     *     capabilities?: array{delete: bool|null, trash: bool|null, move_to_processed: bool|null, rename_processed: bool|null},
     *     recommended_actions?: array<string>,
     *     notice?: string,
     * }
     */
    public function testConnection(array $credentials, string $folderId, ?string $processedFolderId = null): array
    {
        try {
            $client = $this->createClient($credentials);
            $service = new Drive($client);

            try {
                $response = $service->files->listFiles([
                    'q' => "'{$folderId}' in parents and trashed=false",
                    'pageSize' => 100,
                    'fields' => 'files(id, name)',
                    'includeItemsFromAllDrives' => true,
                    'supportsAllDrives' => true,
                ]);
            } catch (\Google\Service\Exception $e) {
                if ($e->getCode() === 404) {
                    return [
                        'success' => false,
                        'message' => __('Folder not found. Ensure the folder ID is correct and shared with the service account email.'),
                    ];
                }

                if ($e->getCode() === 403) {
                    return [
                        'success' => false,
                        'message' => __('Folder not accessible. Share the folder with the service account email (found in the JSON key as "client_email") and grant at least "Viewer" permissions in Google Drive.'),
                    ];
                }

                throw $e;
            }

            $allFiles = $response->getFiles();
            $fileCount = 0;
            $testFileId = null;

            foreach ($allFiles as $file) {
                $name = $file->getName();

                if ($name === self::EXCLUDED_FILE_NAME) {
                    $testFileId = $file->getId();
                    continue;
                }

                if (! $this->isExcludedFromImport($name)) {
                    $fileCount++;
                }
            }

            $folderName = null;

            try {
                $folderName = $this->getFolderName($folderId, $credentials);
            } catch (Exception) {
                // Folder name is cosmetic — failure is non-blocking
            }

            $base = [
                'success' => true,
                'message' => __('Connection successful'),
                'file_count' => $fileCount,
                'folder_name' => $folderName,
            ];

            if ($testFileId !== null) {
                return array_merge($base, $this->probeCapabilitiesWithRealFile(
                    $service,
                    $testFileId,
                    $folderId,
                    $processedFolderId
                ));
            }

            return array_merge($base, $this->probeCapabilitiesWithTempFile(
                $service,
                $credentials,
                $folderId,
                $processedFolderId
            ));
        } catch (Exception $e) {
            Log::error('Google Drive connection test failed', [
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
                'folder_id' => $folderId,
            ]);

            return [
                'success' => false,
                'message' => __('Connection failed: :error', ['error' => $e->getMessage()]),
            ];
        }
    }

    /**
     * Probe capabilities using the real user-placed "yaffa.txt" file.
     *
     * @return array{test_file_found: true, capabilities_source: string, capabilities: array, recommended_actions: array, notice?: string}
     */
    private function probeCapabilitiesWithRealFile(
        Drive $service,
        string $testFileId,
        string $folderId,
        ?string $processedFolderId
    ): array {
        $capabilities = [
            'delete' => null,
            'trash' => null,
            'move_to_processed' => null,
            'rename_processed' => null,
        ];

        // 1. Try delete
        try {
            $service->files->delete($testFileId, ['supportsAllDrives' => true]);
            $capabilities['delete'] = true;

            return [
                'test_file_found' => true,
                'capabilities_source' => 'real_file',
                'capabilities' => $capabilities,
                'recommended_actions' => ['delete'],
                'notice' => __('The test file was deleted. Re-create :file in the import folder to test other actions.', [
                    'file' => self::EXCLUDED_FILE_NAME,
                ]),
            ];
        } catch (\Google\Service\Exception $e) {
            if ($e->getCode() === 404) {
                $capabilities['delete'] = true;

                return [
                    'test_file_found' => true,
                    'capabilities_source' => 'real_file',
                    'capabilities' => $capabilities,
                    'recommended_actions' => ['delete'],
                ];
            }

            if (! in_array($e->getCode(), [401, 403], true)) {
                throw $e;
            }

            $capabilities['delete'] = false;
        }

        // 2. Try trash
        try {
            $service->files->update(
                $testFileId,
                new DriveFile(['trashed' => true]),
                ['supportsAllDrives' => true]
            );
            $capabilities['trash'] = true;

            return [
                'test_file_found' => true,
                'capabilities_source' => 'real_file',
                'capabilities' => $capabilities,
                'recommended_actions' => ['trash'],
            ];
        } catch (\Google\Service\Exception $e) {
            if ($e->getCode() === 404) {
                $capabilities['trash'] = true;

                return [
                    'test_file_found' => true,
                    'capabilities_source' => 'real_file',
                    'capabilities' => $capabilities,
                    'recommended_actions' => ['trash'],
                ];
            }

            if (! in_array($e->getCode(), [401, 403], true)) {
                throw $e;
            }

            $capabilities['trash'] = false;
        }

        // 3. Try move_to_processed
        if ($processedFolderId) {
            try {
                $service->files->update(
                    $testFileId,
                    new DriveFile(),
                    [
                        'addParents' => $processedFolderId,
                        'removeParents' => $folderId,
                        'supportsAllDrives' => true,
                    ]
                );
                $capabilities['move_to_processed'] = true;
                $capabilities['rename_processed'] = true;

                return [
                    'test_file_found' => true,
                    'capabilities_source' => 'real_file',
                    'capabilities' => $capabilities,
                    'recommended_actions' => ['move_to_processed', 'rename_processed'],
                ];
            } catch (\Google\Service\Exception $e) {
                if ($e->getCode() === 404) {
                    $capabilities['move_to_processed'] = true;
                    $capabilities['rename_processed'] = true;

                    return [
                        'test_file_found' => true,
                        'capabilities_source' => 'real_file',
                        'capabilities' => $capabilities,
                        'recommended_actions' => ['move_to_processed', 'rename_processed'],
                    ];
                }

                if (! in_array($e->getCode(), [401, 403], true)) {
                    throw $e;
                }

                $capabilities['move_to_processed'] = false;
            }
        } else {
            $capabilities['move_to_processed'] = null;
        }

        // 4. Try rename_processed
        try {
            $service->files->update(
                $testFileId,
                new DriveFile(['name' => self::PROCESSED_PREFIX . self::EXCLUDED_FILE_NAME]),
                ['supportsAllDrives' => true]
            );
            $capabilities['rename_processed'] = true;

            $recommended = array_values(array_filter(
                ['move_to_processed', 'rename_processed'],
                fn ($a) => $capabilities[$a] === true
            ));

            return [
                'test_file_found' => true,
                'capabilities_source' => 'real_file',
                'capabilities' => $capabilities,
                'recommended_actions' => $recommended,
            ];
        } catch (\Google\Service\Exception $e) {
            if ($e->getCode() === 404) {
                $capabilities['rename_processed'] = true;

                return [
                    'test_file_found' => true,
                    'capabilities_source' => 'real_file',
                    'capabilities' => $capabilities,
                    'recommended_actions' => ['rename_processed'],
                ];
            }

            if (! in_array($e->getCode(), [401, 403], true)) {
                throw $e;
            }

            $capabilities['rename_processed'] = false;
        }

        return [
            'test_file_found' => true,
            'capabilities_source' => 'real_file',
            'capabilities' => $capabilities,
            'recommended_actions' => [],
        ];
    }

    /**
     * Probe capabilities by creating a temporary file owned by the service account.
     * Results are marked as "estimated" since the service account always owns its own files.
     *
     * @return array{test_file_found: false, capabilities_source: string, capabilities: array, recommended_actions: array}
     */
    private function probeCapabilitiesWithTempFile(
        Drive $service,
        array $credentials,
        string $folderId,
        ?string $processedFolderId
    ): array {
        $tempFileId = null;

        try {
            $tempFileName = 'yaffa_cap_' . Str::random(8) . '.tmp';
            $tempFile = $service->files->create(
                new DriveFile(['name' => $tempFileName, 'parents' => [$folderId]]),
                [
                    'data' => '',
                    'mimeType' => 'text/plain',
                    'uploadType' => 'multipart',
                    'fields' => 'id',
                    'supportsAllDrives' => true,
                ]
            );
            $tempFileId = $tempFile->getId();
        } catch (Exception $e) {
            Log::warning('Could not create temp file for Google Drive capability check', ['error' => $e->getMessage()]);

            return [
                'test_file_found' => false,
                'capabilities_source' => 'estimated',
                'capabilities' => [
                    'delete' => null,
                    'trash' => null,
                    'move_to_processed' => null,
                    'rename_processed' => null,
                ],
                'recommended_actions' => [],
            ];
        }

        $capabilities = [
            'delete' => null,
            'trash' => null,
            'move_to_processed' => null,
            'rename_processed' => null,
        ];

        $deleted = false;

        // Test delete
        try {
            $service->files->delete($tempFileId, ['supportsAllDrives' => true]);
            $capabilities['delete'] = true;
            $deleted = true;
        } catch (\Google\Service\Exception $e) {
            if (in_array($e->getCode(), [401, 403], true)) {
                $capabilities['delete'] = false;
            }
        }

        // Re-create temp file if it was deleted
        if ($deleted) {
            try {
                $newFile = $service->files->create(
                    new DriveFile(['name' => Str::random(8) . '.tmp', 'parents' => [$folderId]]),
                    ['data' => '', 'mimeType' => 'text/plain', 'uploadType' => 'multipart', 'fields' => 'id', 'supportsAllDrives' => true]
                );
                $tempFileId = $newFile->getId();
                $deleted = false;
            } catch (Exception) {
                return [
                    'test_file_found' => false,
                    'capabilities_source' => 'estimated',
                    'capabilities' => $capabilities,
                    'recommended_actions' => array_keys(array_filter($capabilities, fn ($v) => $v === true)),
                ];
            }
        }

        // Test trash
        try {
            $service->files->update(
                $tempFileId,
                new DriveFile(['trashed' => true]),
                ['supportsAllDrives' => true]
            );
            $capabilities['trash'] = true;
            $service->files->update(
                $tempFileId,
                new DriveFile(['trashed' => false]),
                ['supportsAllDrives' => true]
            );
        } catch (\Google\Service\Exception $e) {
            if (in_array($e->getCode(), [401, 403], true)) {
                $capabilities['trash'] = false;
            }
        }

        // Test move_to_processed
        if ($processedFolderId) {
            try {
                $service->files->update(
                    $tempFileId,
                    new DriveFile(),
                    ['addParents' => $processedFolderId, 'removeParents' => $folderId, 'supportsAllDrives' => true]
                );
                $capabilities['move_to_processed'] = true;
                $capabilities['rename_processed'] = true;
                $service->files->update(
                    $tempFileId,
                    new DriveFile(),
                    ['addParents' => $folderId, 'removeParents' => $processedFolderId, 'supportsAllDrives' => true]
                );
            } catch (\Google\Service\Exception $e) {
                if (in_array($e->getCode(), [401, 403], true)) {
                    $capabilities['move_to_processed'] = false;
                }
            }
        }

        // Test rename_processed (only if not already inferred from move)
        if ($capabilities['rename_processed'] === null) {
            try {
                $service->files->update(
                    $tempFileId,
                    new DriveFile(['name' => self::PROCESSED_PREFIX . 'test.tmp']),
                    ['supportsAllDrives' => true]
                );
                $capabilities['rename_processed'] = true;
            } catch (\Google\Service\Exception $e) {
                if (in_array($e->getCode(), [401, 403], true)) {
                    $capabilities['rename_processed'] = false;
                }
            }
        }

        // Clean up
        try {
            $service->files->delete($tempFileId, ['supportsAllDrives' => true]);
        } catch (Exception) {
            // Best-effort cleanup
        }

        $recommended = array_keys(array_filter($capabilities, fn ($v) => $v === true));

        return [
            'test_file_found' => false,
            'capabilities_source' => 'estimated',
            'capabilities' => $capabilities,
            'recommended_actions' => $recommended,
        ];
    }

    /**
     * Determine whether a file name should be excluded from normal import scans.
     */
    public function isExcludedFromImport(string $name): bool
    {
        return $name === self::EXCLUDED_FILE_NAME
            || Str::startsWith($name, self::PROCESSED_PREFIX);
    }

    /**
     * Create a Drive service instance from service account credentials.
     * Override in tests to inject a mock Drive service.
     */
    protected function createDriveService(array $credentials): Drive
    {
        return new Drive($this->createClient($credentials));
    }

    /**
     * Create Google Drive client with service account credentials.
     */
    protected function createClient(array $credentials): Client
    {
        $client = new Client();
        $client->setAuthConfig($credentials);
        $client->addScope(Drive::DRIVE);

        return $client;
    }
}
