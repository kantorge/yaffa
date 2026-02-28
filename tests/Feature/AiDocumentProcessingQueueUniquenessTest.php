<?php

namespace Tests\Feature;

use App\Jobs\AiProcessingJob;
use App\Models\AiDocument;
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
}
