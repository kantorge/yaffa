<?php

namespace Tests\Feature;

use App\Jobs\GoogleDriveMonitorJob;
use App\Jobs\ProcessGoogleDriveConfigJob;
use App\Models\AiUserSettings;
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

        $user = $this->createUserWithAiEnabled();
        GoogleDriveConfig::factory()->create(['user_id' => $user->id, 'enabled' => true]);

        (new GoogleDriveMonitorJob())->handle();

        Queue::assertNotPushed(ProcessGoogleDriveConfigJob::class);
    }

    public function test_job_dispatches_config_job_for_each_enabled_config(): void
    {
        config(['ai-documents.google_drive.enabled' => true]);

        Queue::fake();

        $user1 = $this->createUserWithAiEnabled();
        $user2 = $this->createUserWithAiEnabled();

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

        $user = $this->createUserWithAiEnabled();
        GoogleDriveConfig::factory()->create([
            'user_id' => $user->id,
            'enabled' => false,
        ]);

        (new GoogleDriveMonitorJob())->handle();

        Queue::assertNotPushed(ProcessGoogleDriveConfigJob::class);
    }

    public function test_job_skips_configs_for_users_with_ai_disabled(): void
    {
        config(['ai-documents.google_drive.enabled' => true]);

        Queue::fake();

        $user = User::factory()->create();
        AiUserSettings::factory()->create(['user_id' => $user->id, 'ai_enabled' => false]);

        GoogleDriveConfig::factory()->create([
            'user_id' => $user->id,
            'enabled' => true,
        ]);

        (new GoogleDriveMonitorJob())->handle();

        Queue::assertNotPushed(ProcessGoogleDriveConfigJob::class);
    }

    public function test_job_uses_each_configs_sync_interval_for_dispatch_eligibility(): void
    {
        config(['ai-documents.google_drive.enabled' => true]);

        Queue::fake();

        $user = $this->createUserWithAiEnabled();

        $dueConfig = GoogleDriveConfig::factory()->create([
            'user_id' => $user->id,
            'enabled' => true,
            'sync_interval_minutes' => 10,
            'last_sync_at' => now()->subMinutes(11),
        ]);

        $notDueConfig = GoogleDriveConfig::factory()->create([
            'user_id' => $user->id,
            'enabled' => true,
            'sync_interval_minutes' => 30,
            'last_sync_at' => now()->subMinutes(20),
        ]);

        (new GoogleDriveMonitorJob())->handle();

        Queue::assertPushed(ProcessGoogleDriveConfigJob::class, 1);
        Queue::assertPushed(
            ProcessGoogleDriveConfigJob::class,
            fn (ProcessGoogleDriveConfigJob $job) => $job->configId === $dueConfig->id
        );
        Queue::assertNotPushed(
            ProcessGoogleDriveConfigJob::class,
            fn (ProcessGoogleDriveConfigJob $job) => $job->configId === $notDueConfig->id
        );
    }

    private function createUserWithAiEnabled(): User
    {
        $user = User::factory()->create();
        AiUserSettings::factory()->enabled()->create(['user_id' => $user->id]);

        return $user;
    }
}
