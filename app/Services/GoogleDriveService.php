<?php

namespace App\Services;

use Google\Client;
use Google\Service\Drive;
use Illuminate\Support\Facades\Log;
use Exception;

class GoogleDriveService
{
    /**
     * List files in the Google Drive folder, by default since last_sync_at.
     *
     * @param \App\Models\GoogleDriveConfig $config
     * @return array
     */
    public function listNewFiles($config, bool $sinceLastSync = true): array
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
            ];
            if ($pageToken) {
                $params['pageToken'] = $pageToken;
            }
            $response = $service->files->listFiles($params);
            foreach ($response->getFiles() as $file) {
                $files[] = [
                    'id' => $file->getId(),
                    'name' => $file->getName(),
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
     *
     * @param string $fileId
     * @param array $credentials
     * @param string $destination
     */
    public function downloadFile(string $fileId, array $credentials, string $destination): void
    {
        $client = $this->createClient($credentials);
        $service = new Drive($client);
        $content = $service->files->get($fileId, ['alt' => 'media']);
        file_put_contents($destination, $content->getBody()->getContents());
    }

    /**
     * Delete a file from Google Drive.
     *
     * @param string $fileId
     * @param array $credentials
     */
    public function deleteFile(string $fileId, array $credentials): void
    {
        $client = $this->createClient($credentials);
        $service = new Drive($client);
        $service->files->delete($fileId);
    }

    /**
     * Test connection to Google Drive and return file count and delete permission status
     *
     * @param array $credentials Decoded service account JSON
     * @param string $folderId Google Drive folder ID
     * @return array ['success' => bool, 'file_count' => int, 'has_delete_permission' => bool, 'message' => string]
     */
    public function testConnection(array $credentials, string $folderId): array
    {
        try {
            $client = $this->createClient($credentials);
            $service = new Drive($client);

            // Test folder accessibility by listing files
            try {
                $response = $service->files->listFiles([
                    'q' => "'{$folderId}' in parents and trashed=false",
                    'pageSize' => 10,
                    'fields' => 'files(id, name)',
                ]);

                $fileCount = count($response->getFiles());

                // Check delete permission by checking if we can get folder permissions
                $hasDeletePermission = $this->checkDeletePermission($service, $folderId);

                return [
                    'success' => true,
                    'file_count' => $fileCount,
                    'has_delete_permission' => $hasDeletePermission,
                    'message' => __('Connection successful'),
                ];
            } catch (\Google\Service\Exception $e) {
                // Folder not accessible or not shared with service account
                if ($e->getCode() === 404) {
                    return [
                        'success' => false,
                        'file_count' => 0,
                        'has_delete_permission' => false,
                        'message' => __('Folder not found. Ensure the folder ID is correct and shared with the service account email.'),
                    ];
                }

                if ($e->getCode() === 403) {
                    return [
                        'success' => false,
                        'file_count' => 0,
                        'has_delete_permission' => false,
                        'message' => __('Folder not accessible. Share the folder with the service account email (found in the JSON key as "client_email") and grant at least "Viewer" permissions in Google Drive.'),
                    ];
                }

                throw $e;
            }
        } catch (Exception $e) {
            Log::error('Google Drive connection test failed', [
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
                'folder_id' => $folderId,
            ]);

            return [
                'success' => false,
                'file_count' => 0,
                'has_delete_permission' => false,
                'message' => __('Connection failed: :error', ['error' => $e->getMessage()]),
            ];
        }
    }

    /**
     * Create Google Drive client with service account credentials
     *
     * @param array $credentials Decoded service account JSON
     * @return Client
     */
    private function createClient(array $credentials): Client
    {
        $client = new Client();
        $client->setAuthConfig($credentials);
        $client->addScope(Drive::DRIVE); // Full Drive access (needed for delete operations)
        $client->setSubject(null); // No domain-wide delegation needed

        return $client;
    }

    /**
     * Check if the service account has permissions to add and delete files.
     *
     * This is inferred by checking `canAddChildren`, which indicates write access.
     * For a service account, this is sufficient to delete files it creates.
     *
     * @param Drive $service
     * @param string $folderId
     * @return bool
     */
    private function checkDeletePermission(Drive $service, string $folderId): bool
    {
        try {
            // Get folder metadata with capabilities
            $folder = $service->files->get($folderId, [
                'fields' => 'capabilities,ownedByMe',
            ]);

            $capabilities = $folder->getCapabilities();

            // If we own the folder, we have full permissions
            if ($folder->getOwnedByMe()) {
                return true;
            }

            // Check if we can add/delete children (indicates write access)
            // canAddChildren means we can create/delete files in the folder
            // If capabilities don't give us the answer, assume no delete permission
            return (bool) ($capabilities && $capabilities->getCanAddChildren());
        } catch (Exception $e) {
            Log::warning('Could not check delete permissions', [
                'folder_id' => $folderId,
                'error' => $e->getMessage(),
            ]);

            // If we can't check, assume no delete permission
            return false;
        }
    }
}
