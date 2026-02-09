<?php

namespace App\Listeners;

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
        try {
            Mail::to($event->document->user->email)
                ->send(new AiDocumentProcessedMail($event->document));

            Log::info("Success notification sent for document {$event->document->id}");
        } catch (Exception $e) {
            Log::error("Failed to send processing success email: {$e->getMessage()}");
        }
    }
}
