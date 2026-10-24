<?php

namespace App\Listeners;

use App\Enums\AiDocumentStatus;
use App\Events\AiDocumentProcessedEvent;
use App\Mail\AiDocumentProcessed as AiDocumentProcessedMail;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendAiDocumentProcessedNotification implements ShouldQueue
{
    /**
     * Handle the event.
     */
    public function handle(AiDocumentProcessedEvent $event): void
    {
        // A document closed as a duplicate, or held for its receipt, needs nothing from the user
        if (in_array($event->document->status, [AiDocumentStatus::Duplicate->value, AiDocumentStatus::AwaitingItemization->value], true)) {
            return;
        }

        try {
            Mail::to($event->document->user->email)
                ->locale($event->document->user->language)
                ->send(new AiDocumentProcessedMail($event->document));

            Log::info("Success notification sent for document {$event->document->id}");
        } catch (Exception $e) {
            Log::error("Failed to send processing success email: {$e->getMessage()}");
        }
    }
}
