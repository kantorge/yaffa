<?php

namespace App\Console\Commands;

use App\Services\GmailImapService;
use Exception;
use Illuminate\Console\Command;

class FetchGmailEmails extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'yaffa:fetch-gmail-emails';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch unread emails from Gmail via IMAP and process them';

    protected GmailImapService $gmailService;

    public function __construct(GmailImapService $gmailService)
    {
        parent::__construct();
        $this->gmailService = $gmailService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (!$this->gmailService->isEnabled()) {
            $this->error('Gmail IMAP is not enabled or not configured properly.');
            $this->info('Please set GMAIL_API_ENABLED=true and configure credentials in .env');
            return Command::FAILURE;
        }

        $this->info('Fetching emails from Gmail...');

        try {
            $processedEmails = $this->gmailService->fetchUnreadEmails();

            if (empty($processedEmails)) {
                $this->info('No new emails to process.');
                return Command::SUCCESS;
            }

            $this->info(sprintf('Successfully processed %d email(s):', count($processedEmails)));
            
            foreach ($processedEmails as $email) {
                $this->line(sprintf(
                    '  - From: %s | Subject: %s',
                    $email['from'],
                    $email['subject']
                ));
            }

            return Command::SUCCESS;

        } catch (Exception $e) {
            $this->error('Failed to fetch Gmail emails: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}

