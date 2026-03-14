<?php

namespace App\Jobs;

use App\Events\AiDocumentProcessedEvent;
use App\Events\AiDocumentProcessingFailedEvent;
use App\Models\AiDocument;
use App\Services\AiUserSettingsResolver;
use App\Services\ProcessDocumentService;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Log;

class AiProcessingJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 300;

    public int $uniqueFor = 1800;

    public function __construct(
        public AiDocument $document
    ) {
        $this->onQueue('default');
    }

    /**
     * Get the unique ID for the job lock.
     */
    public function uniqueId(): string
    {
        return (string) $this->document->id;
    }

    /**
     * Execute the job
     */
    public function handle(ProcessDocumentService $service, AiUserSettingsResolver $settingsResolver): void
    {
        $document = $this->document->fresh(['user']);

        if (! $document) {
            return;
        }

        if (! $settingsResolver->isEnabledForUser($document->user)) {
            Log::info("Skipping document {$document->id} processing because AI is disabled for user {$document->user_id}");

            if ($document->status !== 'ready_for_processing') {
                $document->status = 'ready_for_processing';
                $document->save();
            }

            return;
        }

        try {
            // Process the document
            $result = $service->process($document);

            // Success - document status already updated to ready_for_review by service
            Log::info("Document {$document->id} processed successfully");

            // Dispatch success event
            AiDocumentProcessedEvent::dispatch($document);
        } catch (Exception $e) {
            Log::error("Document {$document->id} processing failed: {$e->getMessage()}");

            // Dispatch failure event
            AiDocumentProcessingFailedEvent::dispatch(
                $document,
                $e->getMessage(),
                $e::class,
                (int) $e->getCode(),
            );

            // Don't retry on auth/quota errors
            if ($this->shouldNotRetry($e->getMessage())) {
                $this->fail($e);
            }

            // Otherwise, allow automatic retry
            throw $e;
        }
    }

    /**
     * Determine if we should not retry based on error message
     */
    private function shouldNotRetry(string $errorMessage): bool
    {
        $noRetryPatterns = [
            'invalid.*api.*key',
            'unauthorized',
            'authentication.*failed',
            'quota.*exceeded',
            'rate.*limit',
            'no.*ai.*provider',
        ];

        $lowerMessage = mb_strtolower($errorMessage);

        foreach ($noRetryPatterns as $pattern) {
            if (preg_match("/{$pattern}/i", $lowerMessage)) {
                return true;
            }
        }

        return false;
    }
}
