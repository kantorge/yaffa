<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JobPerformance extends Model
{
    use HasFactory;

    protected $table = 'job_performance';

    protected $fillable = [
        'job_class',
        'job_id',
        'job_parameters',
        'queue',
        'started_at',
        'finished_at',
        'duration_seconds',
        'status',
        'error_message',
        'memory_peak_mb',
        'queries_count',
    ];

    protected $casts = [
        'job_parameters' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'duration_seconds' => 'decimal:3',
        'memory_peak_mb' => 'integer',
        'queries_count' => 'integer',
    ];

    /**
     * Scope to filter by job class
     */
    public function scopeForJobClass($query, string $jobClass)
    {
        return $query->where('job_class', $jobClass);
    }

    /**
     * Scope to filter by status
     */
    public function scopeWithStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to get slow jobs (over a certain threshold in seconds)
     */
    public function scopeSlow($query, float $thresholdSeconds = 60)
    {
        return $query->where('duration_seconds', '>', $thresholdSeconds);
    }

    /**
     * Scope to get recent jobs
     */
    public function scopeRecent($query, int $days = 7)
    {
        return $query->where('started_at', '>=', now()->subDays($days));
    }
}
