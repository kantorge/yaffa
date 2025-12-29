<?php

namespace App\Console\Commands;

use App\Models\JobPerformance;
use Illuminate\Console\Command;

class ShowJobPerformance extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'jobs:show {id : The job performance record ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Show detailed information about a specific job performance record';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $id = $this->argument('id');
        
        $job = JobPerformance::find($id);
        
        if (!$job) {
            $this->error("Job performance record with ID {$id} not found.");
            return 1;
        }

        $this->info("=== Job Performance Details ===");
        $this->newLine();
        
        $this->table(
            ['Field', 'Value'],
            [
                ['ID', $job->id],
                ['Job Class', $job->job_class],
                ['Job ID', $job->job_id ?? 'N/A'],
                ['Queue', $job->queue],
                ['Status', $job->status],
                ['Started At', $job->started_at->format('Y-m-d H:i:s')],
                ['Finished At', $job->finished_at?->format('Y-m-d H:i:s') ?? 'Still running'],
                ['Duration', $job->duration_seconds ? number_format((float) $job->duration_seconds, 3) . 's' : 'N/A'],
                ['Memory Peak', $job->memory_peak_mb ? number_format($job->memory_peak_mb) . 'MB' : 'N/A'],
                ['Query Count', $job->queries_count ? number_format($job->queries_count) : 'N/A'],
            ]
        );

        if ($job->job_parameters) {
            $this->newLine();
            $this->info("Parameters:");
            foreach ($job->job_parameters as $key => $value) {
                $this->line("  {$key}: {$value}");
            }
        }

        if ($job->error_message) {
            $this->newLine();
            $this->error("Error Message:");
            $this->line($job->error_message);
        }

        // Show running time if still running
        if ($job->status === 'running') {
            $this->newLine();
            $duration = now()->diffInMinutes($job->started_at);
            $this->warn("This job is still running for " . number_format($duration) . " minutes!");
        }

        return 0;
    }
}
