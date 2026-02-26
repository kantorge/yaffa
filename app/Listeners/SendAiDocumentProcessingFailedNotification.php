<?php

namespace App\Listeners;

use App\Events\AiDocumentProcessingFailedEvent;
use App\Mail\AiDocumentProcessingFailed as AiDocumentProcessingFailedMail;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendAiDocumentProcessingFailedNotification implements ShouldQueue
{
    /**
     * Handle the event.
     */
    public function handle(AiDocumentProcessingFailedEvent $event): void
    {
        try {
            Mail::to($event->document->user->email)
                ->locale($event->document->user->language)
                ->send(new AiDocumentProcessingFailedMail(
                    $event->document,
                    $event->errorMessage,
                    $event->exceptionClass,
                    $event->errorCode,
                ));

            Log::info("Failure notification sent for document {$event->document->id}");
        } catch (Exception $e) {
            Log::error("Failed to send processing failure email: {$e->getMessage()}");
        }
    }
}
