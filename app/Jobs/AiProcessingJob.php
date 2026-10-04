<?php

namespace App\Jobs;

use App\Events\AiDocumentProcessedEvent;
use App\Events\AiDocumentProcessingFailedEvent;
use App\Models\AiDocument;
use App\Services\AiUserSettingsResolver;
use App\Services\DuplicateDetectionService;
use App\Services\ProcessDocumentService;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Queue\Attributes\UniqueFor;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

#[Tries(3)]
#[Timeout(300)]
#[UniqueFor(1800)]
class AiProcessingJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

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
    public function handle(
        ProcessDocumentService $service,
        AiUserSettingsResolver $settingsResolver,
        ?DuplicateDetectionService $duplicateService = null,
    ): void {
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

            $this->flagDuplicates($document, $duplicateService ?? app(DuplicateDetectionService::class));

            // Dispatch success event
            AiDocumentProcessedEvent::dispatch($document);
        } catch (Exception $e) {
            Log::error("Document {$document->id} processing failed: {$e->getMessage()}");

            // Don't retry on auth/quota errors
            if ($this->shouldNotRetry($e->getMessage())) {
                $this->fail($e);

                return;
            }

            // Otherwise, allow automatic retry
            throw $e;
        }
    }

    /**
     * Store likely duplicates on the document, for the reviewer. Advisory only, so a failure here
     * must never fail the processing itself.
     */
    private function flagDuplicates(AiDocument $document, DuplicateDetectionService $duplicateService): void
    {
        try {
            $document = $document->fresh();
            $data = $document?->processed_transaction_data;

            if (! is_array($data)) {
                return;
            }

            $data['duplicate_candidates'] = $duplicateService->findForDocument($document);
            $document->processed_transaction_data = $data;
            $document->save();
        } catch (Throwable $e) {
            Log::warning("Duplicate check failed for document {$document->id}: {$e->getMessage()}");
        }
    }

    /**
     * Handle a terminal job failure (max attempts exceeded, timeout, or manual fail).
     */
    public function failed(?Throwable $exception): void
    {
        if (! $exception) {
            return;
        }

        $document = $this->document->fresh(['user']);

        if (! $document) {
            return;
        }

        AiDocumentProcessingFailedEvent::dispatch(
            $document,
            $exception->getMessage(),
            $exception::class,
            (int) $exception->getCode(),
        );
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
