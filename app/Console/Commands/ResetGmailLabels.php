<?php

namespace App\Console\Commands;

use App\Services\GmailImapService;
use Exception;
use Illuminate\Console\Command;

class ResetGmailLabels extends Command
{
    protected $signature = 'yaffa:reset-gmail-labels';
    protected $description = 'Remove YAFFA_PROCESSED label from all Gmail messages';

    protected GmailImapService $gmailService;

    public function __construct(GmailImapService $gmailService)
    {
        parent::__construct();
        $this->gmailService = $gmailService;
    }

    public function handle(): int
    {
        if (!$this->gmailService->isEnabled()) {
            $this->error('Gmail API is not enabled.');
            return Command::FAILURE;
        }

        if (!$this->option('no-interaction') && !$this->confirm('This will remove the YAFFA_PROCESSED label from ALL emails. Continue?')) {
            return Command::SUCCESS;
        }

        try {
            $service = $this->gmailService->getGmailService();
            $userEmail = config('yaffa.gmail.user_email') ?? 'me';

            // Get the label ID
            $labels = $service->users_labels->listUsersLabels($userEmail);
            $labelId = null;
            
            foreach ($labels->getLabels() as $label) {
                if ($label->getName() === 'YAFFA_PROCESSED') {
                    $labelId = $label->getId();
                    break;
                }
            }

            if (!$labelId) {
                $this->info('YAFFA_PROCESSED label not found.');
                return Command::SUCCESS;
            }

            $this->info("Found label ID: {$labelId}");

            // Get all messages with this label
            $results = $service->users_messages->listUsersMessages(
                $userEmail,
                ['labelIds' => [$labelId], 'maxResults' => 500]
            );

            $messages = $results->getMessages();

            if (empty($messages)) {
                $this->info('No messages have the YAFFA_PROCESSED label.');
                return Command::SUCCESS;
            }

            $this->info('Found ' . count($messages) . ' messages with the label.');
            $bar = $this->output->createProgressBar(count($messages));
            $bar->start();

            foreach ($messages as $message) {
                try {
                    $service->users_messages->modify(
                        $userEmail,
                        $message->getId(),
                        new \Google\Service\Gmail\ModifyMessageRequest([
                            'removeLabelIds' => [$labelId]
                        ])
                    );
                    $bar->advance();
                } catch (Exception $e) {
                    $this->error("\nError removing label from message: " . $e->getMessage());
                }
            }

            $bar->finish();
            $this->newLine();
            $this->info('Successfully removed label from all messages!');
            $this->info('You can now run: php artisan yaffa:fetch-gmail-emails');

            return Command::SUCCESS;

        } catch (Exception $e) {
            $this->error('Error: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
