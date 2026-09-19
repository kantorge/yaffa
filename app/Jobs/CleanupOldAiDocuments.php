<?php

namespace App\Jobs;

use App\Enums\AiDocumentStatus;
use App\Mail\AiDocumentsAwaitingAction;
use App\Models\AiDocument;
use App\Models\User;
use App\Services\AiUserSettingsResolver;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

/**
 * Applies the user's AI document retention setting (`document_retention_days`) to their documents.
 *
 * Finalized documents past the retention period are deleted together with their files (and the
 * emptied per-document directories) and any received email. Old documents that are not finalized
 * are never deleted; the user gets one reminder email per run instead.
 */
class CleanupOldAiDocuments implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly int $userId)
    {
    }

    public function handle(AiUserSettingsResolver $aiUserSettingsResolver): void
    {
        $user = User::query()->find($this->userId);
        $retentionDays = $user === null
            ? 0
            : (int) $aiUserSettingsResolver->resolveForUser($user)['document_retention_days'];

        if ($retentionDays <= 0) {
            return;
        }

        $cutoff = now()->subDays($retentionDays);
        $oldDocuments = AiDocument::query()->where('user_id', $user->id)->olderThan($cutoff);

        (clone $oldDocuments)
            ->where('status', AiDocumentStatus::Finalized->value)
            ->with(['files', 'receivedMail'])
            ->chunkById(100, fn ($documents) => $documents->each($this->purge(...)));

        $unprocessedCount = (clone $oldDocuments)
            ->where('status', '!=', AiDocumentStatus::Finalized->value)
            ->count();

        if ($unprocessedCount > 0) {
            Mail::to($user->email)
                ->locale($user->language)
                ->send(new AiDocumentsAwaitingAction($user, $unprocessedCount, $retentionDays, $cutoff));
        }
    }

    private function purge(AiDocument $document): void
    {
        $filePaths = $document->files->pluck('file_path')->filter()->all();

        DB::transaction(function () use ($document): void {
            // Cascades to ai_document_files; a linked transaction only loses its reference.
            $document->delete();
            $document->receivedMail?->delete();
        });

        $disk = Storage::disk('local');
        $disk->delete($filePaths);

        foreach (array_unique(array_map(dirname(...), $filePaths)) as $directory) {
            // Only the per-document directory (ai_documents/{user}/{id or uuid}), never a parent of it.
            if (dirname($directory) === "ai_documents/{$document->user_id}" && $disk->allFiles($directory) === []) {
                $disk->deleteDirectory($directory);
            }
        }
    }
}
