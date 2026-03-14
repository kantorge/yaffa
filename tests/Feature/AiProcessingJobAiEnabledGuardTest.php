<?php

namespace Tests\Feature;

use App\Events\AiDocumentProcessedEvent;
use App\Events\AiDocumentProcessingFailedEvent;
use App\Jobs\AiProcessingJob;
use App\Models\AiDocument;
use App\Models\AiUserSettings;
use App\Models\User;
use App\Services\ProcessDocumentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class AiProcessingJobAiEnabledGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_returns_document_to_ready_for_processing_when_ai_is_disabled(): void
    {
        Event::fake([
            AiDocumentProcessedEvent::class,
            AiDocumentProcessingFailedEvent::class,
        ]);

        $user = User::factory()->create();
        AiUserSettings::factory()->create(['user_id' => $user->id, 'ai_enabled' => false]);

        $document = AiDocument::factory()->for($user)->create([
            'status' => 'processing',
        ]);

        $service = $this->createMock(ProcessDocumentService::class);
        $service->expects($this->never())->method('process');

        (new AiProcessingJob($document))->handle($service, app(\App\Services\AiUserSettingsResolver::class));

        $this->assertSame('ready_for_processing', $document->fresh()->status);
        Event::assertNotDispatched(AiDocumentProcessedEvent::class);
        Event::assertNotDispatched(AiDocumentProcessingFailedEvent::class);
    }
}
