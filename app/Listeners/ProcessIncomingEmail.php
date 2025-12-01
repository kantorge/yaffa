<?php

namespace App\Listeners;

use App\Events\IncomingEmailReceived;
use App\Jobs\ProcessIncomingEmailByAi;
use App\Jobs\ProcessLandlordPdfByAi;

class ProcessIncomingEmail
{
    /**
     * Handle the event.
     *
     * @param IncomingEmailReceived $event
     */
    public function handle(IncomingEmailReceived $event): void
    {
        // Check if this is a landlord PDF by looking at the subject
        if (str_contains($event->mail->subject, 'Landlord Statement:')) {
            // Dispatch landlord PDF processing job
            ProcessLandlordPdfByAi::dispatch($event->mail);
        } else {
            // Dispatch regular email processing job
            ProcessIncomingEmailByAi::dispatch($event->mail);
        }
    }
}
