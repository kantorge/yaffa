<?php

namespace App\Console\Commands;

use App\Jobs\AiProcessingJob;
use App\Models\AiDocument;
use Illuminate\Console\Command;

class ProcessAiDocuments extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:process-ai-documents
                            {--limit=10 : Maximum number of documents to process in this run}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process AI documents that are ready for processing';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $limit = (int) $this->option('limit');

        // Get documents ready for processing
        $documents = AiDocument::query()
            ->where('status', 'ready_for_processing')
            ->orderBy('created_at', 'asc')
            ->limit($limit)
            ->get();

        if ($documents->isEmpty()) {
            $this->info('No documents ready for processing');

            return self::SUCCESS;
        }

        $this->info("Found {$documents->count()} document(s) ready for processing");

        $dispatched = 0;

        foreach ($documents as $document) {
            try {
                // Dispatch processing job
                AiProcessingJob::dispatch($document);
                $dispatched++;

                $this->line("✓ Dispatched processing job for document #{$document->id}");
            } catch (\Exception $e) {
                $this->error("✗ Failed to dispatch job for document #{$document->id}: {$e->getMessage()}");
            }
        }

        $this->info("Dispatched {$dispatched} processing job(s)");

        return self::SUCCESS;
    }
}
