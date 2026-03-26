<?php

namespace Tests\Unit\Jobs;

use App\Events\AiDocumentProcessingFailedEvent;
use App\Jobs\AiProcessingJob;
use App\Models\AiDocument;
use App\Models\AiUserSettings;
use App\Services\AiUserSettingsResolver;
use App\Services\ProcessDocumentService;
use Exception;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Mockery;
use Tests\TestCase;

class AiProcessingJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_is_unique_per_document(): void
    {
        /** @var AiDocument $document */
        $document = AiDocument::factory()->create();

        $job = new AiProcessingJob($document);

        $this->assertInstanceOf(ShouldBeUnique::class, $job);
        $this->assertSame((string) $document->id, $job->uniqueId());
    }

    public function test_retryable_exception_does_not_dispatch_failure_event_in_handle(): void
    {
        Event::fake([AiDocumentProcessingFailedEvent::class]);

        /** @var AiDocument $document */
        $document = AiDocument::factory()->create();
        AiUserSettings::factory()->enabled()->create(['user_id' => $document->user_id]);

        $service = Mockery::mock(ProcessDocumentService::class);
        $service->shouldReceive('process')
            ->once()
            ->with(Mockery::on(fn (AiDocument $jobDocument): bool => $jobDocument->is($document)))
            ->andThrow(new Exception('Temporary API timeout', 0));

        $settingsResolver = app(AiUserSettingsResolver::class);

        $job = new AiProcessingJob($document);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Temporary API timeout');

        $job->handle($service, $settingsResolver);

        Event::assertNotDispatched(AiDocumentProcessingFailedEvent::class);
    }

    public function test_failed_dispatches_failure_event_with_serializable_error_fields(): void
    {
        Event::fake([AiDocumentProcessingFailedEvent::class]);

        /** @var AiDocument $document */
        $document = AiDocument::factory()->create();
        AiUserSettings::factory()->enabled()->create(['user_id' => $document->user_id]);

        $job = new AiProcessingJob($document);
        $exception = new Exception('No AI provider configured for user', 401);

        $job->failed($exception);

        Event::assertDispatched(AiDocumentProcessingFailedEvent::class, fn (AiDocumentProcessingFailedEvent $event): bool => $event->document->is($document)
                && $event->errorMessage === 'No AI provider configured for user'
                && $event->exceptionClass === Exception::class
                && $event->errorCode === 401);
    }
}
