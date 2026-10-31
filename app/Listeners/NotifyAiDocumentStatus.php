<?php

namespace App\Listeners;

use App\Enums\AiDocumentStatus;
use App\Events\AiDocumentProcessedEvent;
use App\Events\AiDocumentProcessingFailedEvent;
use App\Notifications\AiDocumentStatusNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Raises the in-app (database) notifications; push channels can later be added to the notification itself
 * without touching the code that dispatches the events.
 */
// Queued so a notification failure can never fail (and re-run) the AI processing job that raised the event.
class NotifyAiDocumentStatus implements ShouldQueue
{
    public function handleProcessed(AiDocumentProcessedEvent $event): void
    {
        // Same rule as the email listener: a duplicate, or a document held for its receipt, needs nothing from the user
        if (in_array($event->document->status, [AiDocumentStatus::Duplicate->value, AiDocumentStatus::AwaitingItemization->value], true)) {
            return;
        }

        $event->document->user->notify(
            new AiDocumentStatusNotification($event->document, AiDocumentStatusNotification::READY_FOR_REVIEW)
        );
    }

    public function handleFailed(AiDocumentProcessingFailedEvent $event): void
    {
        $event->document->user->notify(
            new AiDocumentStatusNotification($event->document, AiDocumentStatusNotification::PROCESSING_FAILED)
        );
    }
}
