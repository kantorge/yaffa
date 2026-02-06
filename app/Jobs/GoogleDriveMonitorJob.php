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

    public function handle(): void
    {
        // Skip if Google Drive feature is disabled
        if (! config('ai-documents.google_drive.enabled')) {
            return;
        }

        // Collect all enabled configs and dispatch a job for each
        $configs = GoogleDriveConfig::query()->where('enabled', true)->get();

        foreach ($configs as $config) {
            ProcessGoogleDriveConfigJob::dispatch($config->id);
        }
    }
}
