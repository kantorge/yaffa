<?php

namespace App\Console\Commands;

use App\Models\JobPerformance;
use Illuminate\Console\Command;

class CleanupStuckJobs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'jobs:cleanup-stuck 
                            {--hours=2 : Number of hours before considering a job stuck}
                            {--dry-run : Show what would be done without actually doing it}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up jobs that have been running for too long and are likely stuck';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $hours = (int) $this->option('hours');
        $dryRun = $this->option('dry-run');

        $this->info("Looking for jobs running for over {$hours} hours...");

        $stuckJobs = JobPerformance::where('status', 'running')
            ->where('started_at', '<', now()->subHours($hours))
            ->orderBy('started_at')
            ->get();

        if ($stuckJobs->isEmpty()) {
            $this->info('No stuck jobs found.');
            return 0;
        }

        $this->warn("Found {$stuckJobs->count()} stuck jobs:");
        $this->newLine();

        $tableData = $stuckJobs->map(function ($job) {
            $duration = now()->diffInHours($job->started_at);
            $params = $job->job_parameters ?? [];
            $paramStr = collect($params)
                ->map(fn($v, $k) => "{$k}:{$v}")
                ->implode(', ');
            
            return [
                $job->id,
                substr($job->job_class, strrpos($job->job_class, '\\') + 1),
                $job->started_at->format('Y-m-d H:i'),
                number_format($duration) . ' hrs',
                substr($paramStr, 0, 50),
            ];
        })->toArray();

        $this->table(
            ['ID', 'Job Class', 'Started', 'Running For', 'Parameters'],
            $tableData
        );

        if ($dryRun) {
            $this->info('Dry run mode - no changes made.');
            return 0;
        }

        if ($this->confirm('Mark these jobs as failed?', true)) {
            $updated = JobPerformance::where('status', 'running')
                ->where('started_at', '<', now()->subHours($hours))
                ->update([
                    'status' => 'failed',
                    'error_message' => "Job timeout - running for over {$hours} hours",
                ]);

            $this->info("Marked {$updated} jobs as failed.");
            
            // Also restart the queue workers to prevent them from continuing to process stuck jobs
            $this->info('Restarting queue workers...');
            $this->call('queue:restart');
            
            return 0;
        }

        $this->info('Cancelled.');
        return 1;
    }
}
