<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigrateCalculationJobsToCorrectQueue extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'queue:migrate-calculation-jobs {--dry-run : Show what would be migrated without actually migrating}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate CalculateAccountMonthlySummary jobs from default queue to calculations queue';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $isDryRun = $this->option('dry-run');

        if ($isDryRun) {
            $this->info('Running in DRY RUN mode - no changes will be made');
            $this->newLine();
        }

        // Find all CalculateAccountMonthlySummary jobs in the default queue
        $jobsToMigrate = DB::table('jobs')
            ->where('queue', 'default')
            ->where('payload', 'like', '%CalculateAccountMonthlySummary%')
            ->get();

        if ($jobsToMigrate->isEmpty()) {
            $this->info('No CalculateAccountMonthlySummary jobs found in the default queue.');
            return self::SUCCESS;
        }

        $this->info("Found {$jobsToMigrate->count()} CalculateAccountMonthlySummary job(s) in the default queue.");
        $this->newLine();

        $migratedCount = 0;

        foreach ($jobsToMigrate as $job) {
            // Decode the payload to verify it's actually our job
            $payload = json_decode($job->payload, true);
            $displayName = $payload['displayName'] ?? 'Unknown';

            if (!str_contains($displayName, 'CalculateAccountMonthlySummary')) {
                continue;
            }

            $this->line("Job ID {$job->id}: {$displayName}");

            if (!$isDryRun) {
                // Update the queue field to 'calculations'
                DB::table('jobs')
                    ->where('id', $job->id)
                    ->update(['queue' => 'calculations']);

                $this->info("  → Migrated to 'calculations' queue");
                $migratedCount++;
            } else {
                $this->comment("  → Would be migrated to 'calculations' queue");
                $migratedCount++;
            }
        }

        $this->newLine();

        if ($isDryRun) {
            $this->info("DRY RUN: Would have migrated {$migratedCount} job(s).");
            $this->comment('Run without --dry-run to actually migrate the jobs.');
        } else {
            $this->info("Successfully migrated {$migratedCount} job(s) to the 'calculations' queue.");
            $this->comment('Remember to ensure you have a queue worker running for the calculations queue:');
            $this->line('  php artisan queue:work --queue=calculations,default');
        }

        return self::SUCCESS;
    }
}
