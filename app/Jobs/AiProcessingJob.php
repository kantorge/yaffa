<?php

namespace App\Jobs;

use App\Events\AiDocumentProcessedEvent;
use App\Events\AiDocumentProcessingFailedEvent;
use App\Models\AiDocument;
use App\Services\ProcessDocumentService;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Log;

class AiProcessingJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 300;

    public function __construct(
        public AiDocument $document
    ) {
        $this->onQueue('default');
    }

    /**
     * Execute the job
     */
    public function handle(ProcessDocumentService $service): void
    {
        try {
            // Process the document
            $result = $service->process($this->document);

            // Success - document status already updated to ready_for_review by service
            Log::info("Document {$this->document->id} processed successfully");

            // Dispatch success event
            AiDocumentProcessedEvent::dispatch($this->document);
        } catch (Exception $e) {
            Log::error("Document {$this->document->id} processing failed: {$e->getMessage()}");

            // Dispatch failure event
            AiDocumentProcessingFailedEvent::dispatch($this->document, $e);

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
