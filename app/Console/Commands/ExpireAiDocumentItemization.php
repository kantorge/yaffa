<?php

namespace App\Console\Commands;

use App\Enums\AiDocumentStatus;
use App\Models\AiDocument;
use App\Services\AiUserSettingsResolver;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

#[Signature('ai-documents:expire-itemization')]
#[Description('Move documents that waited too long for their receipt to review')]
class ExpireAiDocumentItemization extends Command
{
    public function handle(AiUserSettingsResolver $settingsResolver): int
    {
        $count = 0;

        AiDocument::query()
            ->with('user')
            ->where('status', AiDocumentStatus::AwaitingItemization->value)
            ->chunkById(100, function ($documents) use ($settingsResolver, &$count): void {
                foreach ($documents as $document) {
                    $days = (int) $settingsResolver->resolveForUser($document->user)['itemization_timeout_days'];

                    // Same per-user lock as ProcessDocumentService::decideOutcome(), so a merge cannot race the expiry
                    Cache::lock("ai-decide:{$document->user_id}", 60)->block(30, function () use ($document, $days, &$count): void {
                        DB::transaction(function () use ($document, $days, &$count): void {
                            $locked = AiDocument::query()->lockForUpdate()->find($document->id);

                            if (
                                $locked === null
                                || $locked->status !== AiDocumentStatus::AwaitingItemization->value
                                || $locked->status_changed_at->gt(now()->subDays($days))
                            ) {
                                return;
                            }

                            $draft = $locked->processed_transaction_data ?? [];
                            $draft['auto_record'] = [
                                'failed_gate' => 'completeness',
                                'reason' => __('No receipt within :days days.', ['days' => $days]),
                            ];
                            $locked->processed_transaction_data = $draft;
                            $locked->transitionTo(AiDocumentStatus::ReadyForReview);
                            $count++;
                        });
                    });
                }
            });

        $this->info("Moved {$count} document(s) to review.");

        return Command::SUCCESS;
    }
}
