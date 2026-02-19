<?php

namespace Tests\Unit\Jobs;

use App\Events\AiDocumentProcessingFailedEvent;
use App\Jobs\AiProcessingJob;
use App\Models\AiDocument;
use App\Services\ProcessDocumentService;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Mockery;
use Tests\TestCase;

class AiProcessingJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_dispatches_failure_event_with_serializable_error_fields(): void
    {
        Event::fake([AiDocumentProcessingFailedEvent::class]);

        /** @var AiDocument $document */
        $document = AiDocument::factory()->create();

        $service = Mockery::mock(ProcessDocumentService::class);
        $service->shouldReceive('process')
            ->once()
            ->with($document)
            ->andThrow(new Exception('No AI provider configured for user', 401));

        $job = new AiProcessingJob($document);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('No AI provider configured for user');

        try {
            $job->handle($service);
        } finally {
            Event::assertDispatched(AiDocumentProcessingFailedEvent::class, fn (AiDocumentProcessingFailedEvent $event): bool => $event->document->is($document)
                    && $event->errorMessage === 'No AI provider configured for user'
                    && $event->exceptionClass === Exception::class
                    && $event->errorCode === 401);
        }
    }
}
