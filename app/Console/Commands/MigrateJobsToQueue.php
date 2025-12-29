<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigrateJobsToQueue extends Command
{
    protected $signature = 'queue:migrate-jobs {--from=default} {--to=calculations} {--job=}';

    protected $description = 'Migrate jobs from one queue to another';

    public function handle(): int
    {
        $fromQueue = $this->option('from');
        $toQueue = $this->option('to');
        $jobClass = $this->option('job');

        $query = DB::table('jobs')->where('queue', $fromQueue);

        if ($jobClass) {
            $query->whereRaw("payload LIKE ?", ['%' . $jobClass . '%']);
        }

        $jobs = $query->get();

        if ($jobs->isEmpty()) {
            $this->info("No jobs found in queue '{$fromQueue}'" . ($jobClass ? " matching '{$jobClass}'" : ''));
            return Command::SUCCESS;
        }

        $this->info("Found {$jobs->count()} jobs to migrate from '{$fromQueue}' to '{$toQueue}'");

        if (!$this->confirm('Do you want to proceed?')) {
            $this->info('Migration cancelled');
            return Command::SUCCESS;
        }

        $migrated = 0;
        foreach ($jobs as $job) {
            $payload = json_decode($job->payload, true);
            
            // Update the queue in the payload
            if (isset($payload['data']['commandName'])) {
                DB::table('jobs')
                    ->where('id', $job->id)
                    ->update(['queue' => $toQueue]);
                $migrated++;
            }
        }

        $this->info("Successfully migrated {$migrated} jobs to '{$toQueue}' queue");
        return Command::SUCCESS;
    }
}
