# Job Performance Tracking

This system tracks the performance of Laravel queue jobs to help identify slow-running or problematic jobs.

## Overview

The performance tracking system records:
- Execution time (duration in seconds)
- Memory usage (peak MB)
- Query count
- Job parameters
- Success/failure status
- Error messages for failed jobs

## Usage

### Automatic Tracking

Jobs using the `TracksJobPerformance` trait will automatically record performance metrics. The `CalculateAccountMonthlySummary` job is already configured with this trait.

To add tracking to other jobs:

1. Add the trait to your job:
```php
use App\Jobs\Traits\TracksJobPerformance;

class YourJob implements ShouldQueue
{
    use TracksJobPerformance;
    
    public function handle()
    {
        $this->performWithTracking(function () {
            // Your job logic here
        });
    }
}
```

### Analyzing Performance

**View overall statistics:**
```bash
php artisan jobs:analyze
```

**Analyze specific time period:**
```bash
php artisan jobs:analyze --days=30
```

**Filter by job class:**
```bash
php artisan jobs:analyze --class="App\Jobs\CalculateAccountMonthlySummary"
```

**Change slow job threshold:**
```bash
php artisan jobs:analyze --slow=120  # Jobs over 2 minutes
```

**View specific job details:**
```bash
php artisan jobs:show 123  # Show details for job performance record ID 123
```

## Reports

The `jobs:analyze` command provides:

1. **Overall Statistics**: Total jobs, completed, failed, still running
2. **Performance by Job Class**: Average/max/min duration, memory usage, query count
3. **Top 10 Slowest Jobs**: Individual jobs that took the longest
4. **Recent Failed Jobs**: Jobs that encountered errors
5. **Long-Running Jobs**: Jobs still in "running" status for over 1 hour (possibly stuck)

## Database

Performance data is stored in the `job_performance` table with the following key fields:

- `job_class`: Fully qualified class name
- `job_id`: Unique identifier (from `uniqueId()` method if available)
- `job_parameters`: JSON of job parameters for filtering
- `started_at`, `finished_at`: Timestamps
- `duration_seconds`: Execution time
- `status`: running, completed, failed
- `memory_peak_mb`: Peak memory usage
- `queries_count`: Number of database queries

## Optimization Tips

If you find jobs running for over an hour:

1. Check if they're stuck (status still "running" after hours/days)
2. Review the job parameters to identify patterns (specific accounts, date ranges)
3. Look at query count - high numbers may indicate N+1 problems
4. Check memory usage - may need chunking or pagination
5. Consider breaking large jobs into smaller batches

## Maintenance

Clean up old performance records periodically:

```php
// Delete records older than 30 days
JobPerformance::where('created_at', '<', now()->subDays(30))->delete();
```

Or add to your scheduled tasks in `app/Console/Kernel.php`:

```php
$schedule->call(function () {
    JobPerformance::where('created_at', '<', now()->subDays(30))->delete();
})->weekly();
```
