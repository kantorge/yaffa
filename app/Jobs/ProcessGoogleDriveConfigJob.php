<?php

namespace App\Jobs;

use App\Events\DocumentImported;
use App\Models\AiDocument;
use App\Models\AiDocumentFile;
use App\Models\GoogleDriveConfig;
use App\Notifications\GoogleDriveImportFailed;
use App\Notifications\GoogleDriveImportSuccess;
use App\Services\AiUserSettingsResolver;
use App\Services\GoogleDriveService;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class ProcessGoogleDriveConfigJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public $tries = 3;

    public $timeout = 300;

    public function __construct(
        public int $configId,
        public bool $isManual = false
    ) {
    }

    public function handle(GoogleDriveService $driveService, AiUserSettingsResolver $settingsResolver): void
    {
        $config = GoogleDriveConfig::find($this->configId);

        if (! $config || ! $config->enabled) {
            return;
        }

        $user = $config->user;

        if (! $settingsResolver->isEnabledForUser($user)) {
            Log::info('Skipping Google Drive import because AI processing is disabled for user', [
                'config_id' => $config->id,
                'user_id' => $user->id,
            ]);

            return;
        }

        $credentials = json_decode($config->service_account_json, true);
        $allowedTypes = config('ai-documents.file_upload.allowed_types', []);
        $maxFileSizeMb = config('ai-documents.file_upload.max_file_size_mb', 20);
        $maxFileSizeBytes = $maxFileSizeMb * 1024 * 1024;

        try {
            $newFiles = $driveService->listNewFiles($config, !$this->isManual);
            $stats = [
                'imported' => 0,
                'skipped_existing' => 0,
                'skipped_unsupported' => 0,
                'skipped_too_large' => 0,
                'failed_downloads' => 0,
            ];

            foreach ($newFiles as $file) {
                // Skip if already imported
                if (AiDocument::where('google_drive_file_id', $file['id'])->exists()) {
                    Log::debug('File already imported, skipping', ['file_id' => $file['id']]);
                    $stats['skipped_existing']++;
                    continue;
                }

                // Skip unsupported file types
                $ext = mb_strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                if (! in_array($ext, $allowedTypes)) {
                    Log::debug('File type not allowed, skipping', ['file_id' => $file['id'], 'file_type' => $ext]);
                    $stats['skipped_unsupported']++;
                    continue;
                }

                // Download file
                $storagePath = "ai_documents/{$user->id}/" . Str::uuid() . "/{$file['name']}";
                $fullPath = storage_path('app/' . $storagePath);

                try {
                    // Ensure directory exists before downloading
                    mkdir(dirname($fullPath), 0755, true);
                    $driveService->downloadFile($file['id'], $credentials, $fullPath);
                } catch (Exception $e) {
                    Log::error('Failed to download file from Google Drive', ['file_id' => $file['id'], 'error' => $e->getMessage()]);
                    $stats['failed_downloads']++;
                    continue;
                }

                // Check file size
                if (filesize($fullPath) > $maxFileSizeBytes) {
                    Storage::delete($storagePath);
                    $stats['skipped_too_large']++;
                    continue;
                }

                // Create AiDocument
                $aiDocument = $user->aiDocuments()->create([
                    'status' => 'ready_for_processing',
                    'source_type' => 'google_drive',
                    'google_drive_file_id' => $file['id'],
                ]);

                /** @var AiDocument $aiDocument */

                // Create AiDocumentFile
                AiDocumentFile::create([
                    'ai_document_id' => $aiDocument->id,
                    'file_path' => $storagePath,
                    'file_name' => $file['name'],
                    'file_type' => $ext,
                ]);

                // Fire event
                event(new DocumentImported($aiDocument));
                $stats['imported']++;

                // Delete file from Drive if enabled
                if ($config->delete_after_import) {
                    try {
                        $driveService->deleteFile($file['id'], $credentials, $config->folder_id);
                    } catch (Exception $e) {
                        Log::warning('Failed to delete file from Google Drive', ['file_id' => $file['id'], 'error' => $e->getMessage()]);
                    }
                }
            }

            // Success: reset error state and update sync time
            $config->last_sync_at = now();
            $config->error_count = 0;
            $config->last_error = null;
            $config->save();

            if ($stats['imported'] > 0) {
                $user->notify(new GoogleDriveImportSuccess($config, $stats));
            }
        } catch (Throwable $e) {
            // Error: increment counter and potentially disable
            $config->error_count = ($config->error_count ?? 0) + 1;
            $config->last_error = $e->getMessage();

            if ($e instanceof \Google\Service\Exception && in_array($e->getCode(), [401, 403])) {
                $config->enabled = false;
            }

            $config->save();

            $user->notify(new GoogleDriveImportFailed($config, $e->getMessage()));

            Log::error('Google Drive config processing failed', ['config_id' => $config->id, 'error' => $e->getMessage()]);

            throw $e;
        }
    }
}
