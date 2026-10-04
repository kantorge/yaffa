<?php

namespace App\Listeners;

use App\Events\AiDocumentProcessedEvent;
use App\Events\AiDocumentProcessingFailedEvent;
use App\Notifications\AiDocumentStatusNotification;

/**
 * Raises the in-app (database) notifications; push channels can later be added to the notification itself
 * without touching the code that dispatches the events.
 */
class NotifyAiDocumentStatus
{
    public function handleProcessed(AiDocumentProcessedEvent $event): void
    {
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
