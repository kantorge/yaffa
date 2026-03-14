<?php

namespace App\Jobs;

use App\Models\GoogleDriveConfig;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GoogleDriveMonitorJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public $tries = 1;

    public $timeout = 60;

    private const DEFAULT_SYNC_INTERVAL_MINUTES = 15;

    public function handle(): void
    {
        // Skip if Google Drive feature is disabled
        if (! config('ai-documents.google_drive.enabled')) {
            return;
        }

        $now = now();

        // Collect all enabled configs and dispatch a job for each
        // Check if the configured time interval has passed since the last sync to avoid unnecessary job dispatching
        $configs = GoogleDriveConfig::query()
            ->where('enabled', true)
            ->whereHas('user.aiUserSettings', function ($query) {
                $query->where('ai_enabled', true);
            })
            ->get()
            ->filter(function (GoogleDriveConfig $config) use ($now): bool {
                if ($config->last_sync_at === null) {
                    return true;
                }

                $syncIntervalMinutes = max(1, (int) ($config->sync_interval_minutes ?: self::DEFAULT_SYNC_INTERVAL_MINUTES));

                return $config->last_sync_at->copy()->addMinutes($syncIntervalMinutes)->lessThanOrEqualTo($now);
            })
            ->values();

        foreach ($configs as $config) {
            ProcessGoogleDriveConfigJob::dispatch($config->id);
        }
    }
}
