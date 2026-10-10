<?php

namespace Tests\Concerns;

use App\Models\AiDocument;
use App\Models\AiDocumentFile;
use App\Models\AiProviderConfig;
use App\Models\User;
use App\Services\AiExtractionSchemaValidator;
use App\Services\AiPromptBuilder;
use App\Services\CategoryLearningService;
use App\Services\PayeeCategoryStatsService;
use App\Services\ProcessDocumentService;
use App\Services\TextExtractionService;

/**
 * Runs the real ProcessDocumentService::process() on a new document, with the AI extraction replaced by a
 * fixed payload.
 */
trait ProcessesAiDocuments
{
    /**
     * @param  array<string, mixed>  $payload  Overrides of the default extraction of a parking withdrawal
     * @param  array<string, mixed>  $attributes  Attributes of the AI document
     */
    protected function processDocument(User $user, array $payload, array $attributes = []): AiDocument
    {
        AiProviderConfig::query()->where('user_id', $user->id)->exists()
            || AiProviderConfig::factory()->for($user)->create();

        $document = AiDocument::factory()->for($user)->create([
            'status' => 'ready_for_processing',
            'source_type' => 'manual_upload',
        ] + $attributes);
        AiDocumentFile::factory()->create([
            'ai_document_id' => $document->id,
            'file_path' => 'ai_documents/test/input.txt',
            'file_name' => 'input.txt',
            'file_type' => 'txt',
        ]);

        $textExtractor = $this->createStub(TextExtractionService::class);
        $textExtractor->method('extractFromFile')->willReturn('Receipt text');

        $service = new class (
            $textExtractor,
            new CategoryLearningService(),
            app(PayeeCategoryStatsService::class),
            new AiExtractionSchemaValidator(),
            new AiPromptBuilder(),
        ) extends ProcessDocumentService {
            public array $payload = [];

            protected function requestMainExtractionPayload(AiProviderConfig $config, AiDocument $document, string $prompt): array
            {
                return $this->payload;
            }
        };
        $service->payload = $payload + [
            'transaction_type' => 'withdrawal',
            'account' => null,
            'account_from' => null,
            'account_to' => null,
            'payee' => 'Parking Kft',
            'date' => now()->subDays(30)->toDateString(),
            'amount' => 11,
            'transaction_items' => [],
        ];

        $service->process($document);

        return $document->fresh();
    }
}
