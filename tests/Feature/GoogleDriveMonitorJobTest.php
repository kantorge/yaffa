<?php

namespace Tests\Feature;

use App\Jobs\GoogleDriveMonitorJob;
use App\Jobs\ProcessGoogleDriveConfigJob;
use App\Models\GoogleDriveConfig;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class GoogleDriveMonitorJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_skips_execution_when_google_drive_disabled(): void
    {
        config(['ai-documents.google_drive.enabled' => false]);

        Queue::fake();

        $user = User::factory()->create();
        GoogleDriveConfig::factory()->create(['user_id' => $user->id, 'enabled' => true]);

        (new GoogleDriveMonitorJob())->handle();

        Queue::assertNotPushed(ProcessGoogleDriveConfigJob::class);
    }

    public function test_job_dispatches_config_job_for_each_enabled_config(): void
    {
        config(['ai-documents.google_drive.enabled' => true]);

        Queue::fake();

        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $config1 = GoogleDriveConfig::factory()->create([
            'user_id' => $user1->id,
            'enabled' => true,
        ]);

        $config2 = GoogleDriveConfig::factory()->create([
            'user_id' => $user2->id,
            'enabled' => true,
        ]);

        GoogleDriveConfig::factory()->create([
            'user_id' => $user1->id,
            'enabled' => false,
        ]);

        (new GoogleDriveMonitorJob())->handle();

        Queue::assertPushed(ProcessGoogleDriveConfigJob::class, 2);
        Queue::assertPushed(
            ProcessGoogleDriveConfigJob::class,
            fn (ProcessGoogleDriveConfigJob $job) => $job->configId === $config1->id
        );
        Queue::assertPushed(
            ProcessGoogleDriveConfigJob::class,
            fn (ProcessGoogleDriveConfigJob $job) => $job->configId === $config2->id
        );
    }

    public function test_job_handles_no_enabled_configs(): void
    {
        config(['ai-documents.google_drive.enabled' => true]);

        Queue::fake();

        $user = User::factory()->create();
        GoogleDriveConfig::factory()->create([
            'user_id' => $user->id,
            'enabled' => false,
        ]);

        (new GoogleDriveMonitorJob())->handle();

        Queue::assertNotPushed(ProcessGoogleDriveConfigJob::class);
    }
}
