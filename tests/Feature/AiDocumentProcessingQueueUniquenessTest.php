<?php

namespace Tests\Feature;

use App\Jobs\AiProcessingJob;
use App\Models\AiDocument;
use App\Models\AiUserSettings;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AiDocumentProcessingQueueUniquenessTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_does_not_duplicate_job_dispatched_from_upload_flow(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        AiUserSettings::factory()->enabled()->create(['user_id' => $user->id]);

        $this->actingAs($user, 'sanctum')
            ->postJson(route('api.v1.documents.store'), [
                'text_input' => 'Coffee 4.50 USD',
            ])
            ->assertCreated();

        $this->artisan('app:process-ai-documents', ['--limit' => 10])
            ->assertExitCode(0);

        Queue::assertPushed(AiProcessingJob::class, 1);

        $document = AiDocument::query()->firstOrFail();
        $this->assertSame('processing', $document->fresh()->status);
    }

    public function test_command_does_not_duplicate_job_dispatched_from_reprocess_flow(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        AiUserSettings::factory()->enabled()->create(['user_id' => $user->id]);

        $document = AiDocument::factory()->for($user)->create([
            'status' => 'ready_for_review',
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson(route('api.v1.documents.reprocess', ['aiDocument' => $document]))
            ->assertOk();

        $this->artisan('app:process-ai-documents', ['--limit' => 10])
            ->assertExitCode(0);

        Queue::assertPushed(AiProcessingJob::class, 1);
        $this->assertSame('processing', $document->fresh()->status);
    }

    public function test_command_skips_documents_for_users_with_ai_disabled(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        AiUserSettings::factory()->create(['user_id' => $user->id, 'ai_enabled' => false]);

        $document = AiDocument::factory()->for($user)->create([
            'status' => 'ready_for_processing',
        ]);

        $this->artisan('app:process-ai-documents', ['--limit' => 10])
            ->assertExitCode(0);

        Queue::assertNotPushed(AiProcessingJob::class);
        $this->assertSame('ready_for_processing', $document->fresh()->status);
    }
}
