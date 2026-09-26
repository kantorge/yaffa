<?php

namespace App\Console\Commands;

use App\Jobs\CleanupOldAiDocuments;
use App\Models\AiUserSettings;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('ai-documents:cleanup-old-files {userId? : Optional user ID for scoped cleanup}')]
#[Description('Delete finalized AI documents and their files after the retention period, and remind users about old unprocessed ones')]
class CleanupOldAiDocumentFiles extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $userId = $this->argument('userId');
        if ($userId !== null && User::query()->find((int) $userId) === null) {
            $this->error('Invalid userId');

            return Command::FAILURE;
        }

        // Retention is a per-user AI setting; users without a retention period are left alone.
        $userIds = AiUserSettings::query()
            ->where('document_retention_days', '>', 0)
            ->when($userId !== null, fn ($query) => $query->where('user_id', (int) $userId))
            ->pluck('user_id');

        $userIds->each(fn (int $id) => CleanupOldAiDocuments::dispatch($id));

        $this->info("AI document cleanup dispatched for {$userIds->count()} user(s).");

        return Command::SUCCESS;
    }
}
