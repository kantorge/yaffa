<?php

namespace Tests\Feature;

use App\Models\AiDocument;
use App\Models\AiDocumentFile;
use App\Models\AiUserSettings;
use App\Models\GoogleDriveConfig;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

class AiDocumentStorageAndDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_text_input_is_stored_under_a_readable_file_path(): void
    {
        Queue::fake();
        Storage::fake('local');

        $user = User::factory()->create();
        AiUserSettings::factory()->enabled()->create(['user_id' => $user->id]);
        Sanctum::actingAs($user, ['*']);

        $this->postJson(route('api.v1.documents.store'), ['text_input' => 'Coffee 4.50 USD'])
            ->assertCreated();

        $file = AiDocumentFile::query()->firstOrFail();
        $this->assertSame("ai_documents/{$user->id}/{$file->ai_document_id}/{$file->file_name}", $file->file_path);
        $this->assertSame('Coffee 4.50 USD', Storage::disk('local')->get($file->file_path));
    }

    public function test_a_failed_file_write_leaves_no_document_behind_and_queues_nothing(): void
    {
        Queue::fake();
        Storage::fake('local');
        $disk = Mockery::mock(Storage::disk('local'));
        $disk->shouldReceive('put')->andReturn(false);
        Storage::shouldReceive('disk')->with('local')->andReturn($disk);

        $user = User::factory()->create();
        AiUserSettings::factory()->enabled()->create(['user_id' => $user->id]);
        Sanctum::actingAs($user, ['*']);

        $this->postJson(route('api.v1.documents.store'), ['text_input' => 'Coffee 4.50 USD'])
            ->assertServerError();

        $this->assertDatabaseCount('ai_documents', 0);
        $this->assertDatabaseCount('ai_document_files', 0);
        Queue::assertNothingPushed();
    }

    public function test_deleting_a_document_keeps_its_transaction(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $document = AiDocument::factory()->for($user)->create(['status' => 'finalized']);
        $transaction = Transaction::factory()->for($user)->withdrawal($user)->create([
            'ai_document_id' => $document->id,
        ]);
        Sanctum::actingAs($user, ['*']);

        $this->deleteJson(route('api.v1.documents.destroy', ['aiDocument' => $document]))
            ->assertNoContent();

        $this->assertDatabaseMissing('ai_documents', ['id' => $document->id]);
        $this->assertDatabaseHas('transactions', ['id' => $transaction->id, 'ai_document_id' => null]);
    }

    public function test_migration_repairs_text_input_file_paths_only(): void
    {
        $user = User::factory()->create();
        $document = AiDocument::factory()->for($user)->create();

        $broken = AiDocumentFile::factory()->for($document)->create(['file_path' => '1', 'file_name' => 'text_input_1750000000.txt', 'file_type' => 'txt']);
        $other = AiDocumentFile::factory()->for($document)->create(['file_path' => '1', 'file_name' => 'receipt.txt', 'file_type' => 'txt']);
        $healthy = AiDocumentFile::factory()->for($document)->create(['file_path' => 'ai_documents/9/9/text_input_1.txt', 'file_name' => 'text_input_1.txt', 'file_type' => 'txt']);

        (include database_path('migrations/2026_09_19_000002_fix_text_input_ai_document_file_paths.php'))->up();

        $this->assertSame("ai_documents/{$user->id}/{$document->id}/text_input_1750000000.txt", $broken->fresh()->file_path);
        $this->assertSame('1', $other->fresh()->file_path);
        $this->assertSame('ai_documents/9/9/text_input_1.txt', $healthy->fresh()->file_path);
    }

    public function test_maintenance_page_warns_only_when_an_enabled_drive_config_keeps_imported_files(): void
    {
        $user = User::factory()->create(['language' => 'en']);
        $warning = __('maintenance.aiDocumentOldFiles.driveWarning');

        $this->assertStringNotContainsString($warning, $this->cleanupConfirmText($user));

        GoogleDriveConfig::factory()->for($user)->create(['enabled' => true, 'post_import_actions' => ['rename_processed']]);
        GoogleDriveConfig::factory()->for($user)->create(['enabled' => false, 'post_import_actions' => null]);
        $this->assertStringNotContainsString($warning, $this->cleanupConfirmText($user));

        GoogleDriveConfig::factory()->for($user)->create(['enabled' => true, 'post_import_actions' => []]);
        $this->assertStringContainsString($warning, $this->cleanupConfirmText($user));
    }

    public function test_maintenance_cleanup_button_is_disabled_until_a_retention_period_is_set(): void
    {
        $user = User::factory()->create(['language' => 'en']);
        $disabledMessage = __('maintenance.aiDocumentOldFiles.disabled');

        $html = $this->actingAs($user)->get(route('user.maintenance'))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/<button[^>]*\sdisabled[^>]*cleanup-ai-document-old-files/s', $html);
        $this->assertStringContainsString(e($disabledMessage), $html);

        AiUserSettings::query()->where('user_id', $user->id)->update(['document_retention_days' => 60]);

        $html = $this->actingAs($user)->get(route('user.maintenance'))->assertOk()->getContent();
        $this->assertDoesNotMatchRegularExpression('/<button[^>]*\sdisabled[^>]*cleanup-ai-document-old-files/s', $html);
        $this->assertStringContainsString('older than 60 days', html_entity_decode($html));
    }

    /**
     * The text of the confirmation dialog of the cleanup button (the page also embeds every JS translation).
     */
    private function cleanupConfirmText(User $user): string
    {
        $html = $this->actingAs($user)->get(route('user.maintenance'))->assertOk()->getContent();

        preg_match('/cleanup-ai-document-old-files"[^>]*data-confirm-text="([^"]*)"/s', $html, $matches);

        return html_entity_decode($matches[1] ?? '');
    }
}
