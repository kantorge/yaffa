<?php

namespace Tests\Feature;

use App\Models\AiDocument;
use App\Models\AiDocumentFile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CleanupOldAiDocumentFilesCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_deletes_only_files_older_than_configured_retention(): void
    {
        Storage::fake('local');
        config()->set('ai-documents.local_storage_file_retention.retention_days', 90);

        $user = User::factory()->create();

        $oldDocument = AiDocument::factory()->for($user)->create([
            'created_at' => now()->subDays(95),
        ]);
        $newDocument = AiDocument::factory()->for($user)->create([
            'created_at' => now()->subDays(20),
        ]);

        $oldFilePath = "ai_documents/{$user->id}/{$oldDocument->id}/old.txt";
        $newFilePath = "ai_documents/{$user->id}/{$newDocument->id}/new.txt";

        Storage::disk('local')->put($oldFilePath, 'old');
        Storage::disk('local')->put($newFilePath, 'new');

        AiDocumentFile::factory()->for($oldDocument)->create([
            'file_path' => $oldFilePath,
            'file_name' => 'old.txt',
            'file_type' => 'txt',
        ]);
        AiDocumentFile::factory()->for($newDocument)->create([
            'file_path' => $newFilePath,
            'file_name' => 'new.txt',
            'file_type' => 'txt',
        ]);

        $this->artisan('ai-documents:cleanup-old-files')
            ->assertSuccessful();

        Storage::disk('local')->assertMissing($oldFilePath);
        Storage::disk('local')->assertExists($newFilePath);
    }

    public function test_it_skips_cleanup_when_retention_days_is_zero(): void
    {
        Storage::fake('local');
        config()->set('ai-documents.local_storage_file_retention.retention_days', 0);

        $user = User::factory()->create();
        $document = AiDocument::factory()->for($user)->create([
            'created_at' => now()->subDays(365),
        ]);

        $filePath = "ai_documents/{$user->id}/{$document->id}/kept.txt";
        Storage::disk('local')->put($filePath, 'kept');

        AiDocumentFile::factory()->for($document)->create([
            'file_path' => $filePath,
            'file_name' => 'kept.txt',
            'file_type' => 'txt',
        ]);

        $this->artisan('ai-documents:cleanup-old-files')
            ->assertSuccessful();

        Storage::disk('local')->assertExists($filePath);
    }

    public function test_it_can_scope_cleanup_to_a_specific_user(): void
    {
        Storage::fake('local');
        config()->set('ai-documents.local_storage_file_retention.retention_days', 90);

        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $userDocument = AiDocument::factory()->for($user)->create([
            'created_at' => now()->subDays(100),
        ]);
        $otherUserDocument = AiDocument::factory()->for($otherUser)->create([
            'created_at' => now()->subDays(100),
        ]);

        $userPath = "ai_documents/{$user->id}/{$userDocument->id}/user.txt";
        $otherUserPath = "ai_documents/{$otherUser->id}/{$otherUserDocument->id}/other.txt";

        Storage::disk('local')->put($userPath, 'u');
        Storage::disk('local')->put($otherUserPath, 'o');

        AiDocumentFile::factory()->for($userDocument)->create([
            'file_path' => $userPath,
            'file_name' => 'user.txt',
            'file_type' => 'txt',
        ]);
        AiDocumentFile::factory()->for($otherUserDocument)->create([
            'file_path' => $otherUserPath,
            'file_name' => 'other.txt',
            'file_type' => 'txt',
        ]);

        $this->artisan('ai-documents:cleanup-old-files', ['userId' => $user->id])
            ->assertSuccessful();

        Storage::disk('local')->assertMissing($userPath);
        Storage::disk('local')->assertExists($otherUserPath);
    }

    public function test_it_fails_when_user_id_is_invalid(): void
    {
        $this->artisan('ai-documents:cleanup-old-files', ['userId' => 999999])
            ->assertFailed();
    }
}
