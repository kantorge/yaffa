<?php

namespace App\Console\Commands;

use App\Enums\AiDocumentStatus;
use App\Models\AiDocument;
use App\Services\AiUserSettingsResolver;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

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
            ->each(function (AiDocument $document) use ($settingsResolver, &$count): void {
                $days = (int) $settingsResolver->resolveForUser($document->user)['itemization_timeout_days'];

                if ($document->status_changed_at->gt(now()->subDays($days))) {
                    return;
                }

                $draft = $document->processed_transaction_data ?? [];
                $draft['auto_record'] = [
                    'failed_gate' => 'completeness',
                    'reason' => __('No receipt within :days days.', ['days' => $days]),
                ];
                $document->processed_transaction_data = $draft;
                $document->transitionTo(AiDocumentStatus::ReadyForReview);
                $count++;
            });

        $this->info("Moved {$count} document(s) to review.");

        return Command::SUCCESS;
    }
}
