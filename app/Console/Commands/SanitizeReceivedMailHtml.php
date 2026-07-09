<?php

namespace App\Console\Commands;

use App\Models\ReceivedMail;
use App\Services\EmailHtmlSanitizerService;
use Illuminate\Console\Command;

class SanitizeReceivedMailHtml extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:mail:sanitize-received-html {--dry-run : Report affected records without updating them}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Re-sanitize existing ReceivedMail.html records with the current HTML sanitizer (for records stored before sanitization was enforced)';

    public function handle(EmailHtmlSanitizerService $sanitizer): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $query = ReceivedMail::query()->whereNotNull('html')->where('html', '!=', '');

        $total = $query->count();

        if ($total === 0) {
            $this->info('No received mail with HTML content found.');

            return Command::SUCCESS;
        }

        $this->info(sprintf('Found %d received mail record(s) with HTML content.', $total));

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $updated = 0;

        $query->select(['id', 'html'])
            ->chunkById(200, function ($chunk) use ($sanitizer, $dryRun, &$updated, $bar) {
                foreach ($chunk as $receivedMail) {
                    $sanitized = $sanitizer->sanitize($receivedMail->html);

                    if ($sanitized !== $receivedMail->html) {
                        $updated++;

                        if (! $dryRun) {
                            $receivedMail->html = $sanitized;
                            $receivedMail->save();
                        }
                    }

                    $bar->advance();
                }
            });

        $bar->finish();
        $this->newLine();

        if ($dryRun) {
            $this->info(sprintf('Dry run: %d of %d record(s) would be changed.', $updated, $total));
        } else {
            $this->info(sprintf('Sanitized %d of %d record(s).', $updated, $total));
        }

        return Command::SUCCESS;
    }
}
