<?php

namespace App\Console\Commands;

use App\Models\JobPerformance;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AnalyzeJobPerformance extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'jobs:analyze 
                            {--days=7 : Number of days to analyze}
                            {--slow=60 : Threshold in seconds for slow jobs}
                            {--class= : Filter by specific job class}
                            {--status= : Filter by status (running, completed, failed)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Analyze job performance metrics and identify slow-running jobs';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $days = (int) $this->option('days');
        $slowThreshold = (float) $this->option('slow');
        $jobClass = $this->option('class');
        $status = $this->option('status');

        $this->info("Analyzing job performance for the last {$days} days");
        $this->newLine();

        // Build query
        $query = JobPerformance::recent($days);
        
        if ($jobClass) {
            $query->forJobClass($jobClass);
        }
        
        if ($status) {
            $query->withStatus($status);
        }

        $totalJobs = $query->count();
        
        if ($totalJobs === 0) {
            $this->info('No job performance records found.');
            return 0;
        }

        // Overall statistics
        $this->info("=== Overall Statistics ===");
        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Jobs', number_format($totalJobs)],
                ['Completed', number_format($query->clone()->withStatus('completed')->count())],
                ['Failed', number_format($query->clone()->withStatus('failed')->count())],
                ['Still Running', number_format($query->clone()->withStatus('running')->count())],
            ]
        );
        $this->newLine();

        // Performance by job class
        $this->info("=== Performance by Job Class ===");
        $byClass = DB::table('job_performance')
            ->select(
                'job_class',
                DB::raw('COUNT(*) as total'),
                DB::raw('AVG(duration_seconds) as avg_duration'),
                DB::raw('MAX(duration_seconds) as max_duration'),
                DB::raw('MIN(duration_seconds) as min_duration'),
                DB::raw('AVG(memory_peak_mb) as avg_memory'),
                DB::raw('AVG(queries_count) as avg_queries'),
                DB::raw('SUM(CASE WHEN status = "failed" THEN 1 ELSE 0 END) as failed_count')
            )
            ->where('started_at', '>=', now()->subDays($days))
            ->whereNotNull('duration_seconds')
            ->groupBy('job_class')
            ->orderByDesc('avg_duration')
            ->get();

        if ($byClass->isEmpty()) {
            $this->info('No completed jobs with duration data found.');
        } else {
            $tableData = $byClass->map(function ($row) {
                return [
                    substr($row->job_class, strrpos($row->job_class, '\\') + 1),
                    number_format($row->total),
                    number_format($row->avg_duration, 2) . 's',
                    number_format($row->max_duration, 2) . 's',
                    number_format($row->min_duration, 2) . 's',
                    number_format($row->avg_memory, 1) . 'MB',
                    number_format($row->avg_queries),
                    $row->failed_count,
                ];
            })->toArray();

            $this->table(
                ['Job Class', 'Total', 'Avg Time', 'Max Time', 'Min Time', 'Avg Memory', 'Avg Queries', 'Failed'],
                $tableData
            );
        }
        $this->newLine();

        // Slowest individual jobs
        $this->info("=== Top 10 Slowest Jobs (over {$slowThreshold}s) ===");
        $slowJobs = JobPerformance::recent($days)
            ->slow($slowThreshold)
            ->whereNotNull('duration_seconds')
            ->orderByDesc('duration_seconds')
            ->limit(10)
            ->get();

        if ($slowJobs->isEmpty()) {
            $this->info("No jobs slower than {$slowThreshold} seconds found.");
        } else {
            $tableData = $slowJobs->map(function ($job) {
                $params = $job->job_parameters ?? [];
                $paramStr = collect($params)
                    ->map(fn($v, $k) => "{$k}:{$v}")
                    ->take(3)
                    ->implode(', ');
                
                return [
                    $job->id,
                    substr($job->job_class, strrpos($job->job_class, '\\') + 1),
                    number_format($job->duration_seconds, 2) . 's',
                    number_format($job->memory_peak_mb) . 'MB',
                    number_format($job->queries_count),
                    $job->started_at->format('Y-m-d H:i'),
                    $job->status,
                    substr($paramStr, 0, 40),
                ];
            })->toArray();

            $this->table(
                ['ID', 'Job Class', 'Duration', 'Memory', 'Queries', 'Started', 'Status', 'Parameters'],
                $tableData
            );
        }
        $this->newLine();

        // Failed jobs
        $failedJobs = JobPerformance::recent($days)
            ->withStatus('failed')
            ->orderByDesc('started_at')
            ->limit(10)
            ->get();

        if ($failedJobs->isNotEmpty()) {
            $this->warn("=== Recent Failed Jobs ===");
            $tableData = $failedJobs->map(function ($job) {
                return [
                    $job->id,
                    substr($job->job_class, strrpos($job->job_class, '\\') + 1),
                    $job->started_at->format('Y-m-d H:i'),
                    substr($job->error_message ?? '', 0, 60),
                ];
            })->toArray();

            $this->table(
                ['ID', 'Job Class', 'Started', 'Error'],
                $tableData
            );
            $this->newLine();
        }

        // Long-running jobs (still in running status)
        $runningJobs = JobPerformance::withStatus('running')
            ->where('started_at', '<', now()->subHour())
            ->orderBy('started_at')
            ->get();

        if ($runningJobs->isNotEmpty()) {
            $this->warn("=== Long-Running Jobs (over 1 hour, still running) ===");
            $tableData = $runningJobs->map(function ($job) {
                $duration = now()->diffInMinutes($job->started_at);
                $params = $job->job_parameters ?? [];
                $paramStr = collect($params)
                    ->map(fn($v, $k) => "{$k}:{$v}")
                    ->implode(', ');
                
                return [
                    $job->id,
                    substr($job->job_class, strrpos($job->job_class, '\\') + 1),
                    $job->started_at->format('Y-m-d H:i'),
                    number_format($duration) . ' min',
                    substr($paramStr, 0, 50),
                ];
            })->toArray();

            $this->table(
                ['ID', 'Job Class', 'Started', 'Running For', 'Parameters'],
                $tableData
            );
            $this->newLine();
            
            $this->warn("Warning: {$runningJobs->count()} jobs have been running for over an hour!");
            $this->comment("These may be stuck or extremely slow. Consider investigating or killing them.");
        }

        return 0;
    }
}
