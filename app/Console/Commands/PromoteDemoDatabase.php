<?php

namespace App\Console\Commands;

use App\Services\SandboxDemoDataExporter;
use Illuminate\Console\Command;

class PromoteDemoDatabase extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:sandbox:promote-database
        {--force : Skip the confirmation prompt}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Dump the demo (sandbox) database directly into database/seeders/demo.sql, ready for review and commit';

    /**
     * Execute the console command.
     */
    public function handle(SandboxDemoDataExporter $exporter): int
    {
        // This command cannot be run if sandbox mode is not enabled
        if (! config('yaffa.sandbox_mode')) {
            $this->error('This command can only be run in sandbox mode.');
            return Command::FAILURE;
        }

        $path = base_path('database/seeders/demo.sql');

        if ((! $this->option('force')) && (! $this->confirm("This will overwrite {$path} with the current sandbox data. Continue?"))) {
            $this->warn('Aborted.');
            return Command::SUCCESS;
        }

        // Dates are shifted back to the demo.sql anchor baseline before dumping (and restored
        // afterwards), so the file is ready to commit without manually recomputing dates.
        $exporter->export($path);

        $this->info("Demo data written to {$path}.");
        $this->info('Review the changes with: git diff database/seeders/demo.sql');

        return Command::SUCCESS;
    }
}
