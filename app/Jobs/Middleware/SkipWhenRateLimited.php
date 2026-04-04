<?php

namespace App\Jobs\Middleware;

use Closure;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Custom rate-limiting middleware that logs a visible "skipped" message before releasing the job.
 *
 * This is a drop-in replacement for Laravel's built-in {@see \Illuminate\Queue\Middleware\RateLimited}
 * that adds an explicit log entry when a job is held back by a rate limit, making rate-limited
 * releases distinguishable from normal completions in queue log output (e.g. Laravel Pail).
 */
class SkipWhenRateLimited
{
    public function __construct(private readonly string $limiterName)
    {
    }

    public function handle(object $job, Closure $next): mixed
    {
        $callback = RateLimiter::limiter($this->limiterName);

        if ($callback === null) {
            return $next($job);
        }

        foreach (Arr::wrap(call_user_func($callback, $job)) as $limit) {
            if (RateLimiter::tooManyAttempts($limit->key, $limit->maxAttempts)) {
                $retryAfter = RateLimiter::availableIn($limit->key) + 3;

                Log::info('Job skipped due to rate limiting', [
                    'job' => get_class($job),
                    'limit_key' => $limit->key,
                    'retry_after_seconds' => $retryAfter,
                ]);

                $job->release($retryAfter);

                return false;
            }

            RateLimiter::hit($limit->key, $limit->decaySeconds);
        }

        return $next($job);
    }
}
