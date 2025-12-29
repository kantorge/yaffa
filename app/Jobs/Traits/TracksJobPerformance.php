<?php

namespace App\Jobs\Traits;

use App\Models\JobPerformance;
use Illuminate\Support\Facades\DB;

trait TracksJobPerformance
{
    private ?JobPerformance $performanceRecord = null;
    private ?float $startTime = null;
    private ?int $startMemory = null;
    private ?int $startQueryCount = null;

    /**
     * Start tracking job performance
     */
    protected function startPerformanceTracking(): void
    {
        $this->startTime = microtime(true);
        $this->startMemory = memory_get_usage(true);
        
        // Enable query log to count queries
        DB::enableQueryLog();
        $this->startQueryCount = count(DB::getQueryLog());
        
        // Extract job parameters for tracking
        $parameters = $this->getJobParameters();
        
        // Get job ID if available (from uniqueId method)
        $jobId = method_exists($this, 'uniqueId') ? $this->uniqueId() : null;
        
        $this->performanceRecord = JobPerformance::create([
            'job_class' => get_class($this),
            'job_id' => $jobId,
            'job_parameters' => $parameters,
            'queue' => $this->queue ?? 'default',
            'started_at' => now(),
            'status' => 'running',
        ]);
    }

    /**
     * Finish tracking job performance
     */
    protected function finishPerformanceTracking(bool $success = true, ?string $errorMessage = null): void
    {
        if (!$this->performanceRecord || !$this->startTime) {
            return;
        }

        $duration = microtime(true) - $this->startTime;
        $memoryPeak = round((memory_get_peak_usage(true) - $this->startMemory) / 1024 / 1024, 2);
        $queryCount = count(DB::getQueryLog()) - $this->startQueryCount;

        $this->performanceRecord->update([
            'finished_at' => now(),
            'duration_seconds' => round($duration, 3),
            'status' => $success ? 'completed' : 'failed',
            'error_message' => $errorMessage,
            'memory_peak_mb' => max(0, $memoryPeak),
            'queries_count' => $queryCount,
        ]);

        // Disable query log to prevent memory issues
        DB::disableQueryLog();
    }

    /**
     * Extract relevant job parameters for tracking
     */
    protected function getJobParameters(): array
    {
        $params = [];

        // Try to extract common parameters
        if (property_exists($this, 'user')) {
            $params['user_id'] = $this->user->id ?? null;
        }
        
        if (property_exists($this, 'accountEntity')) {
            $params['account_id'] = $this->accountEntity?->id ?? null;
        }
        
        if (property_exists($this, 'task')) {
            $params['task'] = $this->task ?? null;
        }
        
        if (property_exists($this, 'dateFrom')) {
            $params['date_from'] = $this->dateFrom?->format('Y-m-d') ?? null;
        }
        
        if (property_exists($this, 'dateTo')) {
            $params['date_to'] = $this->dateTo?->format('Y-m-d') ?? null;
        }

        return $params;
    }

    /**
     * Wrap the handle method to automatically track performance
     */
    protected function performWithTracking(callable $callback): void
    {
        $this->startPerformanceTracking();

        try {
            $callback();
            $this->finishPerformanceTracking(true);
        } catch (\Throwable $e) {
            $this->finishPerformanceTracking(false, $e->getMessage());
            throw $e;
        }
    }
}
