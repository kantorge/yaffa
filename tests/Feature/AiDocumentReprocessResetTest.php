<?php

namespace Tests\Feature;

use App\Jobs\AiProcessingJob;
use App\Models\AiUserSettings;
use App\Models\AiDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class AiDocumentReprocessResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_reprocess_resets_processed_data_and_chat_history(): void
    {
        Bus::fake();

        $user = User::factory()->create();
        AiUserSettings::factory()->enabled()->create(['user_id' => $user->id]);

        $document = AiDocument::factory()->for($user)->create([
            'status' => 'ready_for_review',
            'processed_transaction_data' => [
                'transaction_type' => 'withdrawal',
                'config_type' => 'standard',
                'config' => [],
                'transaction_items' => [],
            ],
            'ai_chat_history' => [
                [
                    'timestamp' => now()->toIso8601String(),
                    'step' => 'main_extraction',
                    'prompt' => 'Prompt text',
                    'response' => 'Raw response',
                ],
            ],
            'processed_at' => now(),
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/documents/{$document->id}/reprocess")
            ->assertOk()
            ->assertJsonPath('status', 'ready_for_processing');

        $document->refresh();

        $this->assertSame('ready_for_processing', $document->status);
        $this->assertNull($document->processed_transaction_data);
        $this->assertNull($document->ai_chat_history);
        $this->assertNull($document->processed_at);

        Bus::assertDispatched(AiProcessingJob::class);
    }
}
