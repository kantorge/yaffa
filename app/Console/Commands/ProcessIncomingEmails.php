<?php

namespace App\Console\Commands;

use App\Jobs\ProcessIncomingEmailByAi;
use App\Models\ReceivedMail;
use Illuminate\Console\Command;

class ProcessIncomingEmails extends Command
{
    protected $signature = 'app:process-incoming-emails';

    protected $description = 'Manually trigger processing of incoming emails in the database.';

    public function handle(): int
    {
        // Get unprocessed received emails
        $emails = ReceivedMail::unprocessed()->get();

        // Loop all emails and invoke the email processing job
        $emails->each(function ($email) {
            ProcessIncomingEmailByAi::dispatch($email);
        });

        return Command::SUCCESS;
    }
}
