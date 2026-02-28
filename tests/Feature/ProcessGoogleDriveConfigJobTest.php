<?php

namespace Tests\Feature;

use App\Events\DocumentImported;
use App\Jobs\ProcessGoogleDriveConfigJob;
use App\Models\AiDocument;
use App\Models\GoogleDriveConfig;
use App\Models\User;
use App\Notifications\GoogleDriveImportFailed;
use App\Notifications\GoogleDriveImportSuccess;
use App\Services\GoogleDriveService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase;
use Exception;

class ProcessGoogleDriveConfigJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Event::fake();
        Notification::fake();
    }

    public function test_job_skips_execution_when_config_not_found(): void
    {
        $mock = $this->createMockService(['listNewFiles' => fn () => throw new Exception('Should not be called')]);
        $this->instance(GoogleDriveService::class, $mock);

        (new ProcessGoogleDriveConfigJob(999))->handle(app(GoogleDriveService::class));

        Event::assertNotDispatched(DocumentImported::class);
    }

    public function test_job_skips_execution_when_config_disabled(): void
    {
        $user = User::factory()->create();
        $config = GoogleDriveConfig::factory()->create(['user_id' => $user->id, 'enabled' => false]);

        $mock = $this->createMockService(['listNewFiles' => fn () => throw new Exception('Should not be called')]);
        $this->instance(GoogleDriveService::class, $mock);

        (new ProcessGoogleDriveConfigJob($config->id))->handle(app(GoogleDriveService::class));

        Event::assertNotDispatched(DocumentImported::class);
    }

    public function test_job_imports_single_file_and_creates_ai_document(): void
    {
        $user = User::factory()->create();
        $config = GoogleDriveConfig::factory()->neverSynced()->create([
            'user_id' => $user->id,
            'enabled' => true,
        ]);

        $mock = $this->createMockService([
            'listNewFiles' => [
                [
                    'id' => 'file-123',
                    'name' => 'receipt.pdf',
                    'mimeType' => 'application/pdf',
                    'modifiedTime' => '2026-02-06T10:00:00Z',
                ],
            ],
            'downloadFile' => function ($fileId, $creds, $dest) {
                if (!file_exists(dirname($dest))) {
                    mkdir(dirname($dest), 0755, true);
                }
                file_put_contents($dest, 'fake pdf content');
            },
        ]);
        $this->instance(GoogleDriveService::class, $mock);

        (new ProcessGoogleDriveConfigJob($config->id))->handle(app(GoogleDriveService::class));

        $this->assertDatabaseHas('ai_documents', [
            'user_id' => $user->id,
            'source_type' => 'google_drive',
            'google_drive_file_id' => 'file-123',
            'status' => 'ready_for_processing',
        ]);

        $this->assertDatabaseHas('ai_document_files', [
            'file_name' => 'receipt.pdf',
            'file_type' => 'pdf',
        ]);

        Event::assertDispatched(
            DocumentImported::class,
            fn (DocumentImported $event) => $event->aiDocument->google_drive_file_id === 'file-123'
        );

        $config->refresh();
        $this->assertNotNull($config->last_sync_at);
        $this->assertSame(0, $config->error_count);
        $this->assertNull($config->last_error);
    }

    public function test_job_skips_unsupported_file_types(): void
    {
        config([
            'ai-documents.file_upload.allowed_types' => ['pdf', 'jpg', 'jpeg', 'png', 'txt'],
        ]);

        $user = User::factory()->create();
        $config = GoogleDriveConfig::factory()->neverSynced()->create([
            'user_id' => $user->id,
            'enabled' => true,
        ]);

        $mock = $this->createMockService([
            'listNewFiles' => [
                ['id' => 'file-1', 'name' => 'document.docx', 'mimeType' => 'application/vnd.openxmlformats', 'modifiedTime' => '2026-02-06T10:00:00Z'],
                ['id' => 'file-2', 'name' => 'script.exe', 'mimeType' => 'application/x-msdownload', 'modifiedTime' => '2026-02-06T10:00:00Z'],
            ],
            'downloadFile' => fn () => throw new Exception('Should not be called'),
        ]);
        $this->instance(GoogleDriveService::class, $mock);

        (new ProcessGoogleDriveConfigJob($config->id))->handle(app(GoogleDriveService::class));

        $this->assertDatabaseMissing('ai_documents', ['google_drive_file_id' => 'file-1']);
        $this->assertDatabaseMissing('ai_documents', ['google_drive_file_id' => 'file-2']);
        Event::assertNotDispatched(DocumentImported::class);
    }

    public function test_job_skips_files_already_imported(): void
    {
        $user = User::factory()->create();
        $config = GoogleDriveConfig::factory()->neverSynced()->create([
            'user_id' => $user->id,
            'enabled' => true,
        ]);

        $user->aiDocuments()->create([
            'status' => 'ready_for_processing',
            'source_type' => 'google_drive',
            'google_drive_file_id' => 'file-123',
        ]);

        $mock = $this->createMockService([
            'listNewFiles' => [
                ['id' => 'file-123', 'name' => 'duplicate.pdf', 'mimeType' => 'application/pdf', 'modifiedTime' => '2026-02-06T10:00:00Z'],
            ],
            'downloadFile' => fn () => throw new Exception('Should not be called'),
        ]);
        $this->instance(GoogleDriveService::class, $mock);

        (new ProcessGoogleDriveConfigJob($config->id))->handle(app(GoogleDriveService::class));

        $this->assertSame(1, AiDocument::count());
        Event::assertNotDispatched(DocumentImported::class);
    }

    public function test_job_skips_files_exceeding_size_limit(): void
    {
        config([
            'ai-documents.file_upload.max_file_size_mb' => 20,
        ]);

        $user = User::factory()->create();
        $config = GoogleDriveConfig::factory()->neverSynced()->create([
            'user_id' => $user->id,
            'enabled' => true,
        ]);

        $mock = $this->createMockService([
            'listNewFiles' => [
                ['id' => 'file-big', 'name' => 'large.pdf', 'mimeType' => 'application/pdf', 'modifiedTime' => '2026-02-06T10:00:00Z'],
            ],
            'downloadFile' => function ($fileId, $creds, $dest) {
                if (!file_exists(dirname($dest))) {
                    mkdir(dirname($dest), 0755, true);
                }
                // Create a file larger than 20MB
                $handle = fopen($dest, 'w');
                fseek($handle, (21 * 1024 * 1024) - 1, SEEK_SET);
                fwrite($handle, 'x');
                fclose($handle);
            },
        ]);
        $this->instance(GoogleDriveService::class, $mock);

        (new ProcessGoogleDriveConfigJob($config->id))->handle(app(GoogleDriveService::class));

        $this->assertDatabaseMissing('ai_documents', ['google_drive_file_id' => 'file-big']);
        Event::assertNotDispatched(DocumentImported::class);
    }

    public function test_job_continues_after_download_failure(): void
    {
        $user = User::factory()->create();
        $config = GoogleDriveConfig::factory()->neverSynced()->create([
            'user_id' => $user->id,
            'enabled' => true,
        ]);

        $downloadCallCount = 0;
        $mock = $this->createMockService([
            'listNewFiles' => [
                ['id' => 'file-fail', 'name' => 'fail.pdf', 'mimeType' => 'application/pdf', 'modifiedTime' => '2026-02-06T10:00:00Z'],
                ['id' => 'file-ok', 'name' => 'success.pdf', 'mimeType' => 'application/pdf', 'modifiedTime' => '2026-02-06T10:00:00Z'],
            ],
            'downloadFile' => function ($fileId, $creds, $dest) use (&$downloadCallCount) {
                $downloadCallCount++;
                if ($fileId === 'file-fail') {
                    throw new Exception('Download failed');
                }
                if (!file_exists(dirname($dest))) {
                    mkdir(dirname($dest), 0755, true);
                }
                file_put_contents($dest, 'pdf content');
            },
        ]);
        $this->instance(GoogleDriveService::class, $mock);

        (new ProcessGoogleDriveConfigJob($config->id))->handle(app(GoogleDriveService::class));

        $this->assertSame(2, $downloadCallCount);
        $this->assertDatabaseMissing('ai_documents', ['google_drive_file_id' => 'file-fail']);
        $this->assertDatabaseHas('ai_documents', ['google_drive_file_id' => 'file-ok']);

        Event::assertDispatched(
            DocumentImported::class,
            fn (DocumentImported $event) => $event->aiDocument->google_drive_file_id === 'file-ok'
        );
    }

    public function test_job_deletes_file_from_drive_when_enabled(): void
    {
        $user = User::factory()->create();
        $config = GoogleDriveConfig::factory()->neverSynced()->create([
            'user_id' => $user->id,
            'enabled' => true,
            'delete_after_import' => true,
        ]);

        $deleteCallCount = 0;
        $mock = $this->createMockService([
            'listNewFiles' => [
                ['id' => 'file-123', 'name' => 'receipt.pdf', 'mimeType' => 'application/pdf', 'modifiedTime' => '2026-02-06T10:00:00Z'],
            ],
            'downloadFile' => function ($fileId, $creds, $dest) {
                if (!file_exists(dirname($dest))) {
                    mkdir(dirname($dest), 0755, true);
                }
                file_put_contents($dest, 'pdf content');
            },
            'deleteFile' => function ($fileId, $creds) use (&$deleteCallCount) {
                $deleteCallCount++;
                if ($fileId !== 'file-123') {
                    throw new Exception('Unexpected file ID');
                }
            },
        ]);
        $this->instance(GoogleDriveService::class, $mock);

        (new ProcessGoogleDriveConfigJob($config->id))->handle(app(GoogleDriveService::class));

        $this->assertSame(1, $deleteCallCount);
        $this->assertDatabaseHas('ai_documents', ['google_drive_file_id' => 'file-123']);
    }

    public function test_job_continues_after_delete_failure(): void
    {
        $user = User::factory()->create();
        $config = GoogleDriveConfig::factory()->neverSynced()->create([
            'user_id' => $user->id,
            'enabled' => true,
            'delete_after_import' => true,
        ]);

        $mock = $this->createMockService([
            'listNewFiles' => [
                ['id' => 'file-123', 'name' => 'receipt.pdf', 'mimeType' => 'application/pdf', 'modifiedTime' => '2026-02-06T10:00:00Z'],
            ],
            'downloadFile' => function ($fileId, $creds, $dest) {
                if (!file_exists(dirname($dest))) {
                    mkdir(dirname($dest), 0755, true);
                }
                file_put_contents($dest, 'pdf content');
            },
            'deleteFile' => fn () => throw new Exception('Delete failed'),
        ]);
        $this->instance(GoogleDriveService::class, $mock);

        (new ProcessGoogleDriveConfigJob($config->id))->handle(app(GoogleDriveService::class));

        // Document should still be created even if deletion fails
        $this->assertDatabaseHas('ai_documents', ['google_drive_file_id' => 'file-123']);
    }

    public function test_job_handles_api_errors_gracefully(): void
    {
        $user = User::factory()->create();
        $config = GoogleDriveConfig::factory()->neverSynced()->create([
            'user_id' => $user->id,
            'enabled' => true,
        ]);

        $mock = $this->createMockService([
            'listNewFiles' => fn () => throw new Exception('API Error'),
        ]);
        $this->instance(GoogleDriveService::class, $mock);

        $this->expectException(Exception::class);

        (new ProcessGoogleDriveConfigJob($config->id))->handle(app(GoogleDriveService::class));

        $config->refresh();
        $this->assertSame(1, $config->error_count);
        $this->assertStringContainsString('API Error', $config->last_error);
    }

    public function test_job_increments_error_count_on_repeated_errors(): void
    {
        $user = User::factory()->create();
        $config = GoogleDriveConfig::factory()->create([
            'user_id' => $user->id,
            'enabled' => true,
            'error_count' => 2,
        ]);

        $mock = $this->createMockService([
            'listNewFiles' => fn () => throw new Exception('Network timeout'),
        ]);
        $this->instance(GoogleDriveService::class, $mock);

        try {
            (new ProcessGoogleDriveConfigJob($config->id))->handle(app(GoogleDriveService::class));
        } catch (Exception) {
            // Expected
        }

        $config->refresh();
        $this->assertTrue($config->enabled); // Still enabled for non-auth errors
        $this->assertSame(3, $config->error_count);
    }

    public function test_job_resets_error_count_on_success(): void
    {
        $user = User::factory()->create();
        $config = GoogleDriveConfig::factory()->neverSynced()->create([
            'user_id' => $user->id,
            'enabled' => true,
            'error_count' => 2,
            'last_error' => 'Previous error',
        ]);

        $mock = $this->createMockService([
            'listNewFiles' => [
                ['id' => 'file-123', 'name' => 'receipt.pdf', 'mimeType' => 'application/pdf', 'modifiedTime' => '2026-02-06T10:00:00Z'],
            ],
            'downloadFile' => function ($fileId, $creds, $dest) {
                if (!file_exists(dirname($dest))) {
                    mkdir(dirname($dest), 0755, true);
                }
                file_put_contents($dest, 'pdf content');
            },
        ]);
        $this->instance(GoogleDriveService::class, $mock);

        (new ProcessGoogleDriveConfigJob($config->id))->handle(app(GoogleDriveService::class));

        $config->refresh();
        $this->assertSame(0, $config->error_count);
        $this->assertNull($config->last_error);
    }

    public function test_job_sends_success_notification(): void
    {
        $user = User::factory()->create();
        $config = GoogleDriveConfig::factory()->neverSynced()->create([
            'user_id' => $user->id,
            'enabled' => true,
        ]);

        $mock = $this->createMockService([
            'listNewFiles' => [
                ['id' => 'file-123', 'name' => 'receipt.pdf', 'mimeType' => 'application/pdf', 'modifiedTime' => '2026-02-06T10:00:00Z'],
            ],
            'downloadFile' => function ($fileId, $creds, $dest) {
                if (!file_exists(dirname($dest))) {
                    mkdir(dirname($dest), 0755, true);
                }
                file_put_contents($dest, 'pdf content');
            },
        ]);
        $this->instance(GoogleDriveService::class, $mock);

        (new ProcessGoogleDriveConfigJob($config->id))->handle(app(GoogleDriveService::class));

        $this->assertDatabaseHas('ai_documents', ['google_drive_file_id' => 'file-123']);
        Notification::assertSentTo($user, GoogleDriveImportSuccess::class);
    }

    public function test_job_does_not_send_success_notification_when_nothing_imported(): void
    {
        $user = User::factory()->create();
        $config = GoogleDriveConfig::factory()->neverSynced()->create([
            'user_id' => $user->id,
            'enabled' => true,
        ]);

        $mock = $this->createMockService([
            'listNewFiles' => [
                ['id' => 'file-1', 'name' => 'unsupported.exe', 'mimeType' => 'application/octet-stream', 'modifiedTime' => '2026-02-06T10:00:00Z'],
            ],
            'downloadFile' => fn () => throw new Exception('Should not be called'),
        ]);
        $this->instance(GoogleDriveService::class, $mock);

        (new ProcessGoogleDriveConfigJob($config->id))->handle(app(GoogleDriveService::class));

        Notification::assertNotSentTo($user, GoogleDriveImportSuccess::class);
    }

    public function test_job_sends_failure_notification(): void
    {
        $user = User::factory()->create();
        $config = GoogleDriveConfig::factory()->neverSynced()->create([
            'user_id' => $user->id,
            'enabled' => true,
        ]);

        $mock = $this->createMockService([
            'listNewFiles' => fn () => throw new Exception('API Error'),
        ]);
        $this->instance(GoogleDriveService::class, $mock);

        try {
            (new ProcessGoogleDriveConfigJob($config->id))->handle(app(GoogleDriveService::class));
        } catch (Exception) {
            // Expected
        }

        Notification::assertSentTo($user, GoogleDriveImportFailed::class);
    }

    /**
     * Helper to create a mock service with expected method returns/behaviors
     */
    private function createMockService(array $methods): MockObject
    {
        $mock = $this->createMock(GoogleDriveService::class);

        foreach ($methods as $method => $behavior) {
            if (is_callable($behavior)) {
                $mock->method($method)->willReturnCallback($behavior);
            } else {
                $mock->method($method)->willReturn($behavior);
            }
        }

        return $mock;
    }
}
