<?php

namespace App\Jobs;

use App\Mail\AiDocumentProcessingFailed;
use App\Models\AiDocument;
use App\Services\ProcessDocumentService;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Log;

class AiProcessingJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $delay = 30;

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
            // Mark as processing
            $this->document->status = 'processing';
            $this->document->save();

            // Process the document
            $result = $service->process($this->document);

            // Success - document status already updated to ready_for_review by service
            Log::info("Document {$this->document->id} processed successfully");
        } catch (Exception $e) {
            // Mark as failed
            $this->document->status = 'processing_failed';
            $this->document->save();

            Log::error("Document {$this->document->id} processing failed: {$e->getMessage()}");

            // Send failure email
            $this->sendFailureEmail($e);

            // Don't retry on auth/quota errors
            if ($this->shouldNotRetry($e->getMessage())) {
                $this->fail($e);
            }

            // Otherwise, allow automatic retry
            throw $e;
        }
    }

    /**
     * Send email notification on failure
     */
    private function sendFailureEmail(Exception $exception): void
    {
        try {
            Mail::to($this->document->user->email)
                ->send(new AiDocumentProcessingFailed($this->document, $exception));
        } catch (Exception $e) {
            Log::error("Failed to send processing failure email: {$e->getMessage()}");
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

    /**
     * Handle job failure
     */
    public function failed(Exception $exception): void
    {
        Log::error("Job failed for document {$this->document->id} after {$this->attempts()} attempts: {$exception->getMessage()}");

        $this->document->status = 'processing_failed';
        $this->document->save();
    }
}
