<?php

namespace App\Listeners;

use App\Events\IncomingEmailReceived;
use App\Jobs\ProcessIncomingEmailByAi;

class ProcessIncomingEmail
{
    /**
     * Handle the event.
     */
    public function handle(IncomingEmailReceived $event): void
    {
        // Dispatch a job to process the email
        ProcessIncomingEmailByAi::dispatch($event->mail);
    }
}
