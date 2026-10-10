<?php

namespace Tests\Feature;

use App\Jobs\AiProcessingJob;
use App\Models\AiDocument;
use App\Models\AiUserSettings;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AiDocumentApiAiEnabledGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_accepts_and_queues_document_when_ai_processing_is_disabled(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        AiUserSettings::factory()->create(['user_id' => $user->id, 'ai_enabled' => false]);

        Sanctum::actingAs($user, ['*']);

        $this
            ->postJson(route('api.v1.documents.store'), [
                'text_input' => 'Coffee 4.50 USD',
            ])
            ->assertCreated();

        $this->assertSame(1, AiDocument::count());
        Queue::assertPushed(AiProcessingJob::class);
    }

    public function test_reprocess_resets_and_queues_document_when_ai_processing_is_disabled(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        AiUserSettings::factory()->create(['user_id' => $user->id, 'ai_enabled' => false]);

        $document = AiDocument::factory()->for($user)->create([
            'status' => 'ready_for_review',
            'processed_transaction_data' => ['raw' => []],
        ]);

        Sanctum::actingAs($user, ['*']);

        $this
            ->postJson(route('api.v1.documents.reprocess', ['aiDocument' => $document]))
            ->assertOk();

        $this->assertSame('ready_for_processing', $document->fresh()->status);
        Queue::assertPushed(AiProcessingJob::class);
    }
}
