<?php

namespace App\Console\Commands;

use App\Models\AiDocumentFile;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CleanupOldAiDocumentFiles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ai-documents:cleanup-old-files {userId? : Optional user ID for scoped cleanup}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete old AI document files from local storage based on retention settings';

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

        $query = AiDocumentFile::query()
            ->whereHas('aiDocument', function ($builder) use ($cutoffDate, $userId): void {
                $builder->where('created_at', '<', $cutoffDate);

                if ($userId !== null) {
                    $builder->where('user_id', (int) $userId);
                }
            });

        $query->chunkById(200, function ($files) use (&$deletedFileCount, &$missingFileCount): void {
            foreach ($files as $file) {
                if (Storage::disk('local')->exists($file->file_path)) {
                    Storage::disk('local')->delete($file->file_path);
                    $deletedFileCount++;
                } else {
                    $missingFileCount++;
                }
            }
        });

        $this->info("AI document cleanup finished. Deleted: {$deletedFileCount}, Missing: {$missingFileCount}.");

        return Command::SUCCESS;
    }
}
