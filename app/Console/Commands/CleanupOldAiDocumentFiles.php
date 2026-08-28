<?php

namespace App\Console\Commands;

use App\Models\AiDocumentFile;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

#[Signature('ai-documents:cleanup-old-files {userId? : Optional user ID for scoped cleanup}')]
#[Description('Delete old AI document files from local storage based on retention settings')]
class CleanupOldAiDocumentFiles extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $retentionDays = (int) config('ai-documents.local_storage_file_retention.retention_days', 90);

        if ($retentionDays <= 0) {
            $this->info('AI document file cleanup is disabled because retention_days is 0 or empty.');

            return Command::SUCCESS;
        }

        $userId = $this->argument('userId');
        if ($userId !== null) {
            $user = User::query()->find((int) $userId);
            if ($user === null) {
                $this->error('Invalid userId');

                return Command::FAILURE;
            }
        }

        $cutoffDate = now()->subDays($retentionDays);
        $deletedFileCount = 0;
        $missingFileCount = 0;
        $deletedRecordCount = 0;

        $query = AiDocumentFile::query()
            ->whereNotNull('file_path')
            ->where('file_path', '!=', '')
            ->whereHas('aiDocument', function ($builder) use ($cutoffDate, $userId): void {
                $builder->where('created_at', '<', $cutoffDate);

                if ($userId !== null) {
                    $builder->where('user_id', (int) $userId);
                }
            });

        $query->chunkById(200, function ($files) use (&$deletedFileCount, &$missingFileCount, &$deletedRecordCount): void {
            foreach ($files as $file) {
                /** @var AiDocumentFile $file */
                if (Storage::disk('local')->exists($file->file_path)) {
                    Storage::disk('local')->delete($file->file_path);
                    $deletedFileCount++;
                } else {
                    $missingFileCount++;
                }

                $file->delete();
                $deletedRecordCount++;
            }
        });

        $this->info("AI document cleanup finished. Deleted files: {$deletedFileCount}, Missing files: {$missingFileCount}, Deleted records: {$deletedRecordCount}.");

        return Command::SUCCESS;
    }
}
