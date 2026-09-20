<?php

namespace Tests\Feature;

use App\Mail\AiDocumentsAwaitingAction;
use App\Models\AiDocument;
use App\Models\AiDocumentFile;
use App\Models\AiUserSettings;
use App\Models\ReceivedMail;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

/**
 * The queue runs synchronously in tests, so the command also exercises CleanupOldAiDocuments.
 */
class CleanupOldAiDocumentFilesCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Mail::fake();
    }

    private function user(?int $retentionDays = 90, array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        AiUserSettings::factory()->create(['user_id' => $user->id, 'document_retention_days' => $retentionDays]);

        return $user;
    }

    private function document(User $user, string $status, int $ageInDays, ?int $updatedDaysAgo = null, array $attributes = []): AiDocument
    {
        return AiDocument::factory()->for($user)->create([
            'status' => $status,
            'created_at' => now()->subDays($ageInDays),
            'updated_at' => now()->subDays($updatedDaysAgo ?? $ageInDays),
        ] + $attributes);
    }

    private function attachFile(AiDocument $document): AiDocumentFile
    {
        $path = "ai_documents/{$document->user_id}/{$document->id}/file.txt";
        Storage::disk('local')->put($path, 'content');

        return AiDocumentFile::factory()->for($document)->create([
            'file_path' => $path,
            'file_name' => 'file.txt',
            'file_type' => 'txt',
        ]);
    }

    public function test_it_deletes_old_finalized_documents_with_their_files_and_received_mail(): void
    {
        $user = $this->user();
        $mail = ReceivedMail::factory()->for($user)->create();
        $document = $this->document($user, 'finalized', 100, attributes: ['received_mail_id' => $mail->id]);
        $file = $this->attachFile($document);

        $this->artisan('ai-documents:cleanup-old-files')->assertSuccessful();

        $this->assertDatabaseMissing('ai_documents', ['id' => $document->id]);
        $this->assertDatabaseMissing('ai_document_files', ['id' => $file->id]);
        $this->assertDatabaseMissing('received_mails', ['id' => $mail->id]);
        Storage::disk('local')->assertMissing($file->file_path);
        Mail::assertNothingSent();
        Mail::assertNothingQueued();
    }

    public function test_it_keeps_the_transaction_of_a_deleted_document(): void
    {
        $user = $this->user();
        $document = $this->document($user, 'finalized', 100);
        $transaction = Transaction::factory()->for($user)->withdrawal($user)->create([
            'ai_document_id' => $document->id,
        ]);

        $this->artisan('ai-documents:cleanup-old-files')->assertSuccessful();

        $this->assertDatabaseMissing('ai_documents', ['id' => $document->id]);
        $this->assertDatabaseHas('transactions', ['id' => $transaction->id, 'ai_document_id' => null]);
    }

    public function test_it_removes_the_emptied_document_directory_but_not_a_directory_with_other_content(): void
    {
        $user = $this->user();
        $emptied = $this->document($user, 'finalized', 100);
        $emptiedFile = $this->attachFile($emptied);
        $shared = $this->document($user, 'finalized', 100);
        $this->attachFile($shared);
        $strayFile = "ai_documents/{$user->id}/{$shared->id}/not_tracked.txt";
        Storage::disk('local')->put($strayFile, 'stray');

        $this->artisan('ai-documents:cleanup-old-files')->assertSuccessful();

        $this->assertTrue(Storage::disk('local')->directoryMissing(dirname($emptiedFile->file_path)));
        Storage::disk('local')->assertExists($strayFile);
    }

    public function test_it_never_deletes_files_outside_the_users_document_directory(): void
    {
        $user = $this->user();
        $other = $this->user();
        $document = $this->document($user, 'finalized', 100);
        $foreign = "ai_documents/{$other->id}/1/theirs.txt";
        foreach (['unrelated/keep.txt', 'loose/only.txt', $foreign, "ai_documents/{$user->id}/../{$other->id}/1/theirs.txt"] as $path) {
            Storage::disk('local')->put($path, 'keep');
        }
        foreach (['1', 'loose/only.txt', $foreign, "ai_documents/{$user->id}/x/../../{$other->id}/1/theirs.txt"] as $path) {
            AiDocumentFile::factory()->for($document)->create(['file_path' => $path, 'file_name' => 'x.txt', 'file_type' => 'txt']);
        }

        $this->artisan('ai-documents:cleanup-old-files')->assertSuccessful();

        $this->assertDatabaseMissing('ai_documents', ['id' => $document->id]);
        foreach (['unrelated/keep.txt', 'loose/only.txt', $foreign] as $path) {
            Storage::disk('local')->assertExists($path);
        }
    }

    public function test_it_never_deletes_files_reached_through_a_symlink(): void
    {
        $user = $this->user();
        $document = $this->document($user, 'finalized', 100);
        $outside = Storage::disk('local')->path('outside');
        $userRoot = Storage::disk('local')->path("ai_documents/{$user->id}");
        Storage::disk('local')->put('outside/secret.txt', 'keep');
        mkdir($userRoot, 0755, true);
        symlink($outside, "{$userRoot}/{$document->id}");
        AiDocumentFile::factory()->for($document)->create([
            'file_path' => "ai_documents/{$user->id}/{$document->id}/secret.txt",
            'file_name' => 'secret.txt',
            'file_type' => 'txt',
        ]);

        $this->artisan('ai-documents:cleanup-old-files')->assertSuccessful();

        Storage::disk('local')->assertExists('outside/secret.txt');
    }

    public function test_it_keeps_the_document_when_its_files_cannot_be_deleted(): void
    {
        $user = $this->user();
        $document = $this->document($user, 'finalized', 100);
        $this->attachFile($document);
        $disk = Mockery::mock(Storage::disk('local'));
        $disk->shouldReceive('delete')->andReturn(false);
        Storage::shouldReceive('disk')->with('local')->andReturn($disk);

        $this->artisan('ai-documents:cleanup-old-files')->assertSuccessful();

        $this->assertDatabaseHas('ai_documents', ['id' => $document->id]);
        $this->assertDatabaseCount('ai_document_files', 1);
    }

    public function test_it_keeps_finalized_documents_that_are_recent_or_were_updated_recently(): void
    {
        $user = $this->user();
        $recent = $this->document($user, 'finalized', 20);
        $recentlyTouched = $this->document($user, 'finalized', 200, 10);
        $recentFile = $this->attachFile($recent);

        $this->artisan('ai-documents:cleanup-old-files')->assertSuccessful();

        $this->assertDatabaseHas('ai_documents', ['id' => $recent->id]);
        $this->assertDatabaseHas('ai_documents', ['id' => $recentlyTouched->id]);
        Storage::disk('local')->assertExists($recentFile->file_path);
        Mail::assertNothingSent();
    }

    public function test_it_keeps_old_unprocessed_documents_and_sends_one_reminder_per_user(): void
    {
        $user = $this->user(90, ['language' => 'en']);
        $otherUser = $this->user();

        $failed = $this->document($user, 'processing_failed', 100);
        $forReview = $this->document($user, 'ready_for_review', 150);
        $failedFile = $this->attachFile($failed);
        $this->document($user, 'ready_for_review', 10);
        $this->document($otherUser, 'ready_for_review', 10);

        $this->artisan('ai-documents:cleanup-old-files')->assertSuccessful();

        $this->assertDatabaseHas('ai_documents', ['id' => $failed->id]);
        $this->assertDatabaseHas('ai_documents', ['id' => $forReview->id]);
        Storage::disk('local')->assertExists($failedFile->file_path);

        Mail::assertSent(AiDocumentsAwaitingAction::class, 1);
        Mail::assertSent(
            AiDocumentsAwaitingAction::class,
            fn (AiDocumentsAwaitingAction $mail) => $mail->hasTo($user->email)
                && $mail->count === 2
                && $mail->retentionDays === 90
        );
    }

    public function test_reminder_links_to_the_unprocessed_filter_of_the_index_view(): void
    {
        $user = $this->user();
        $this->document($user, 'processing_failed', 100);

        $this->artisan('ai-documents:cleanup-old-files')->assertSuccessful();

        Mail::assertSent(AiDocumentsAwaitingAction::class, function (AiDocumentsAwaitingAction $mail) {
            $mail->assertSeeInHtml(route('ai-documents.index', [
                'status' => 'unprocessed',
                'date_to' => now()->subDays(90)->toDateString(),
            ]));

            return true;
        });
    }

    public function test_it_does_nothing_for_users_without_a_retention_setting(): void
    {
        $user = $this->user(null);
        $userWithoutSettings = User::factory()->create();
        $finalized = $this->document($user, 'finalized', 365);
        $this->document($user, 'ready_for_review', 365);
        $untouched = $this->document($userWithoutSettings, 'finalized', 365);
        $file = $this->attachFile($finalized);

        $this->artisan('ai-documents:cleanup-old-files')->assertSuccessful();

        $this->assertDatabaseHas('ai_documents', ['id' => $finalized->id]);
        $this->assertDatabaseHas('ai_documents', ['id' => $untouched->id]);
        Storage::disk('local')->assertExists($file->file_path);
        Mail::assertNothingSent();
    }

    public function test_each_user_gets_their_own_retention_period(): void
    {
        $shortRetention = $this->user(30);
        $longRetention = $this->user(365);
        $shortDocument = $this->document($shortRetention, 'finalized', 100);
        $longDocument = $this->document($longRetention, 'finalized', 100);
        $this->document($longRetention, 'ready_for_review', 100);
        $this->document($shortRetention, 'ready_for_review', 100);

        $this->artisan('ai-documents:cleanup-old-files')->assertSuccessful();

        $this->assertDatabaseMissing('ai_documents', ['id' => $shortDocument->id]);
        $this->assertDatabaseHas('ai_documents', ['id' => $longDocument->id]);
        // Only the user whose retention has passed is reminded
        Mail::assertSent(AiDocumentsAwaitingAction::class, 1);
        Mail::assertSent(
            AiDocumentsAwaitingAction::class,
            fn (AiDocumentsAwaitingAction $mail) => $mail->hasTo($shortRetention->email) && $mail->retentionDays === 30
        );
    }

    public function test_it_can_scope_cleanup_to_a_specific_user(): void
    {
        $user = $this->user();
        $otherUser = $this->user();
        $userDocument = $this->document($user, 'finalized', 100);
        $otherUserDocument = $this->document($otherUser, 'finalized', 100);
        $this->document($otherUser, 'ready_for_review', 100);

        $this->artisan('ai-documents:cleanup-old-files', ['userId' => $user->id])->assertSuccessful();

        $this->assertDatabaseMissing('ai_documents', ['id' => $userDocument->id]);
        $this->assertDatabaseHas('ai_documents', ['id' => $otherUserDocument->id]);
        Mail::assertNothingSent();
    }

    public function test_it_fails_when_user_id_is_invalid(): void
    {
        $this->artisan('ai-documents:cleanup-old-files', ['userId' => 999999])
            ->assertFailed();
    }
}
