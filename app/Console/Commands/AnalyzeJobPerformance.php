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
                            {--status= : Filter by status (running, completed, failed)}
                            {--fix-stuck : Mark stuck running jobs as failed}
                            {--show-trends : Show performance trends over time}';

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
                DB::raw('MAX(queries_count) as max_queries'),
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
                $data = [
                    mb_substr($row->job_class, mb_strrpos($row->job_class, '\\') + 1),
                    number_format($row->total),
                    number_format($row->avg_duration, 2) . 's',
                    number_format($row->max_duration, 2) . 's',
                    number_format($row->min_duration, 2) . 's',
                    number_format($row->avg_memory, 1) . 'MB',
                    number_format($row->avg_queries) . ' / ' . number_format($row->max_queries),
                    $row->failed_count,
                ];

                // Add warning indicators for problematic jobs
                if ($row->avg_duration > 300) { // 5 minutes
                    $data[2] = '<fg=red>' . $data[2] . '</>';
                }
                if ($row->avg_queries > 10000) {
                    $data[6] = '<fg=yellow>' . $data[6] . '</>';
                }
                if ($row->failed_count > 0) {
                    $data[7] = '<fg=red>' . $data[7] . '</>';
                }

                return $data;
            })->toArray();

            $this->table(
                ['Job Class', 'Total', 'Avg Time', 'Max Time', 'Min Time', 'Avg Memory', 'Queries (Avg/Max)', 'Failed'],
                $tableData
            );

            // Add recommendations
            $this->newLine();
            $slowJobs = $byClass->filter(fn ($j) => $j->avg_duration > 300);
            $highQueryJobs = $byClass->filter(fn ($j) => $j->avg_queries > 10000);

            if ($slowJobs->isNotEmpty()) {
                $this->warn('⚠ Slow jobs detected (avg > 5 minutes): ' . $slowJobs->pluck('job_class')->map(fn ($c) => class_basename($c))->implode(', '));
                $this->comment('  Consider: Adding indexes, reducing batch sizes, or optimizing queries');
            }

            if ($highQueryJobs->isNotEmpty()) {
                $this->warn('⚠ High query count detected (avg > 10K): ' . $highQueryJobs->pluck('job_class')->map(fn ($c) => class_basename($c))->implode(', '));
                $this->comment('  Consider: Eager loading relationships, caching, or reducing N+1 queries');
            }
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
                    ->map(fn ($v, $k) => "{$k}:{$v}")
                    ->take(3)
                    ->implode(', ');

                return [
                    $job->id,
                    mb_substr($job->job_class, mb_strrpos($job->job_class, '\\') + 1),
                    number_format($job->duration_seconds, 2) . 's',
                    number_format($job->memory_peak_mb) . 'MB',
                    number_format($job->queries_count),
                    $job->started_at->format('Y-m-d H:i'),
                    $job->status,
                    mb_substr($paramStr, 0, 40),
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
            $tableData = $failedJobs->map(fn ($job) => [
                $job->id,
                mb_substr($job->job_class, mb_strrpos($job->job_class, '\\') + 1),
                $job->started_at->format('Y-m-d H:i'),
                mb_substr($job->error_message ?? '', 0, 60),
            ])->toArray();

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
            $stuckJobs = $runningJobs->filter(fn ($job) => $job->started_at->lt(now()->subHours(6)));

            $tableData = $runningJobs->map(function ($job) {
                $duration = $job->started_at->diffInMinutes(now(), false);
                $isStuck = $duration > 360; // 6 hours
                $params = $job->job_parameters ?? [];
                $paramStr = collect($params)
                    ->map(fn ($v, $k) => "{$k}:{$v}")
                    ->implode(', ');

                $durationStr = $isStuck
                    ? '<fg=red>' . number_format($duration) . ' min (STUCK)</>'
                    : number_format($duration) . ' min';

                return [
                    $job->id,
                    mb_substr($job->job_class, mb_strrpos($job->job_class, '\\') + 1),
                    $job->started_at->format('Y-m-d H:i'),
                    $durationStr,
                    mb_substr($paramStr, 0, 50),
                ];
            })->toArray();

            $this->table(
                ['ID', 'Job Class', 'Started', 'Running For', 'Parameters'],
                $tableData
            );
            $this->newLine();

            $this->warn("Warning: {$runningJobs->count()} jobs have been running for over an hour!");

            if ($stuckJobs->isNotEmpty()) {
                $this->error("Critical: {$stuckJobs->count()} jobs appear to be STUCK (over 6 hours)!");
                $this->comment("These are likely crashed/orphaned jobs that were never marked as failed.");

                if ($this->option('fix-stuck')) {
                    $this->info("Marking stuck jobs as failed...");
                    $stuckJobs->each(function ($job) {
                        $job->update([
                            'status' => 'failed',
                            'ended_at' => now(),
                            'duration_seconds' => $job->started_at->diffInSeconds(now()),
                            'error_message' => 'Job marked as failed by jobs:analyze - stuck for over 6 hours'
                        ]);
                        $this->line("  ✓ Marked job {$job->id} as failed");
                    });
                } else {
                    $this->comment("Run with --fix-stuck to automatically mark these as failed.");
                    $this->comment("Command: php artisan jobs:analyze --fix-stuck");
                }
            } else {
                $this->comment("These may be legitimately slow jobs. Monitor their progress or consider killing them.");
            }
        }

        // Show performance trends if requested
        if ($this->option('show-trends')) {
            $this->newLine();
            $this->info("=== Performance Trends (Daily) ===");

            $trends = DB::table('job_performance')
                ->select(
                    DB::raw('DATE(started_at) as date'),
                    DB::raw('COUNT(*) as total_jobs'),
                    DB::raw('AVG(duration_seconds) as avg_duration'),
                    DB::raw('AVG(queries_count) as avg_queries'),
                    DB::raw('SUM(CASE WHEN status = "failed" THEN 1 ELSE 0 END) as failed'),
                    DB::raw('SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) as completed')
                )
                ->where('started_at', '>=', now()->subDays($days))
                ->whereNotNull('duration_seconds')
                ->groupBy(DB::raw('DATE(started_at)'))
                ->orderBy('date')
                ->get();

            if ($trends->isNotEmpty()) {
                $tableData = $trends->map(fn ($row) => [
                    $row->date,
                    number_format($row->total_jobs),
                    number_format($row->avg_duration, 2) . 's',
                    number_format($row->avg_queries),
                    $row->failed,
                    $row->completed,
                ])->toArray();

                $this->table(
                    ['Date', 'Total Jobs', 'Avg Duration', 'Avg Queries', 'Failed', 'Completed'],
                    $tableData
                );

                // Detect degradation
                if ($trends->count() >= 3) {
                    $recent = $trends->slice(-3);
                    $avgRecentDuration = $recent->avg('avg_duration');
                    $avgRecentQueries = $recent->avg('avg_queries');

                    $older = $trends->slice(0, min(3, $trends->count() - 3));
                    $avgOlderDuration = $older->avg('avg_duration');
                    $avgOlderQueries = $older->avg('avg_queries');

                    if ($avgRecentDuration > $avgOlderDuration * 1.5) {
                        $this->warn('⚠ Performance degradation detected! Average duration increased by ' .
                            number_format((($avgRecentDuration - $avgOlderDuration) / $avgOlderDuration) * 100, 1) . '%');
                    }

                    if ($avgRecentQueries > $avgOlderQueries * 1.5) {
                        $this->warn('⚠ Query count increased by ' .
                            number_format((($avgRecentQueries - $avgOlderQueries) / $avgOlderQueries) * 100, 1) . '%');
                    }
                }
            }
        }

        return 0;
    }
}
