<?php

namespace Tests\Feature;

use App\Jobs\AiProcessingJob;
use App\Models\AiDocument;
use App\Models\AiUserSettings;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AiDocumentApiAiEnabledGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_returns_forbidden_when_ai_processing_is_disabled(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        AiUserSettings::factory()->create(['user_id' => $user->id, 'ai_enabled' => false]);

        $this->actingAs($user, 'sanctum')
            ->postJson(route('api.v1.documents.store'), [
                'text_input' => 'Coffee 4.50 USD',
            ])
            ->assertForbidden()
            ->assertJsonPath('error.code', 'AI_DISABLED');

        $this->assertSame(0, AiDocument::count());
        Queue::assertNotPushed(AiProcessingJob::class);
    }

    public function test_reprocess_returns_forbidden_when_ai_processing_is_disabled(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        AiUserSettings::factory()->create(['user_id' => $user->id, 'ai_enabled' => false]);

        $document = AiDocument::factory()->for($user)->create([
            'status' => 'ready_for_review',
            'processed_transaction_data' => ['raw' => []],
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson(route('api.v1.documents.reprocess', ['aiDocument' => $document]))
            ->assertForbidden()
            ->assertJsonPath('error.code', 'AI_DISABLED');

        $this->assertSame('ready_for_review', $document->fresh()->status);
        Queue::assertNotPushed(AiProcessingJob::class);
    }
}
