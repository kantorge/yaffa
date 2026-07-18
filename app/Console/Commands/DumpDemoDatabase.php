<?php

namespace App\Console\Commands;

use App\Services\SandboxDemoDataExporter;
use Illuminate\Console\Command;

class DumpDemoDatabase extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:sandbox:dump-database
        {--force-sandbox : Allow running this command even if sandbox mode is not enabled}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Dump the demo (sandbox) database to a SQL file to facilitate making changes to the demo dataset';

    /**
     * Execute the console command.
     */
    public function handle(SandboxDemoDataExporter $exporter): int
    {
        // This command cannot be run if sandbox mode is not enabled
        if ((! config('yaffa.sandbox_mode')) && (! $this->option('force-sandbox'))) {
            $this->error('This command can only be run in sandbox mode.');
            return Command::FAILURE;
        }

        // Dates are shifted back to the demo.sql anchor baseline before dumping (and restored
        // afterwards), so the exported file can be dropped straight into database/seeders/demo.sql
        // without manually recomputing dates.
        $exporter->export(storage_path('export.sql'));

        return Command::SUCCESS;
    }
}
