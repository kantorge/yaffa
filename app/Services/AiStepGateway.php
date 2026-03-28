<?php

namespace App\Services;

use App\Exceptions\AiProviderFailureException;
use App\Exceptions\AiResponseParseException;
use App\Models\AiDocument;
use App\Models\AiProviderConfig;
use Exception;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Schema\ArraySchema;
use Prism\Prism\Schema\EnumSchema;
use Prism\Prism\Schema\NumberSchema;
use Prism\Prism\Schema\ObjectSchema;
use Prism\Prism\Schema\StringSchema;

class AiStepGateway
{
    public function __construct(
        private AiPromptBuilder $aiPromptBuilder,
        private ProcessingHistoryRecorder $processingHistoryRecorder,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function extractMainData(
        AiProviderConfig $config,
        AiDocument $document,
        string $prompt,
        bool $promptChatHistoryEnabled,
    ): array {
        $step = 'main_extraction';

        try {
            $response = $this->executeStructuredRequest(
                config: $config,
                document: $document,
                prompt: $prompt,
                promptChatHistoryEnabled: $promptChatHistoryEnabled,
                schema: $this->buildMainExtractionSchema(),
            );

            $structuredPayload = $response['structured'] ?? null;
            if (! is_array($structuredPayload)) {
                throw new AiResponseParseException($step, 'Main extraction structured payload is not an object.');
            }

            $textResponse = json_encode($structuredPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: 'null';
            $this->processingHistoryRecorder->appendProcessingHistory($document, $step, $prompt, $textResponse);

            return $structuredPayload;
        } catch (AiResponseParseException $e) {
            throw $e;
        } catch (Exception $e) {
            $this->processingHistoryRecorder->appendProcessingHistory($document, $step, $prompt, 'ERROR: ' . $e->getMessage());

            throw AiProviderFailureException::fromException(
                exception: $e,
                step: $step,
                provider: $config->provider,
                model: $config->model,
                timeout: $this->processingHistoryRecorder->isAiCallTimeout($e),
            );
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function matchCategoriesBatch(
        AiProviderConfig $config,
        AiDocument $document,
        string $prompt,
        bool $promptChatHistoryEnabled,
    ): array {
        $step = 'category_batch_matching';

        try {
            $response = $this->executeStructuredRequest(
                config: $config,
                document: $document,
                prompt: $prompt,
                promptChatHistoryEnabled: $promptChatHistoryEnabled,
                schema: $this->buildCategoryMatchingSchema(),
            );

            $structuredPayload = $response['structured'] ?? null;
            if (! is_array($structuredPayload)) {
                throw new AiResponseParseException($step, 'Category batch structured payload is not an object.');
            }

            $matchesPayload = $structuredPayload['matches'] ?? null;
            if (! is_array($matchesPayload)) {
                throw new AiResponseParseException($step, 'Category batch structured payload must contain a matches array.');
            }

            $textResponse = json_encode($matchesPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]';
            $this->processingHistoryRecorder->appendProcessingHistory($document, $step, $prompt, $textResponse);

            return $matchesPayload;
        } catch (AiResponseParseException $e) {
            throw $e;
        } catch (Exception $e) {
            $this->processingHistoryRecorder->appendAiFallbackHistoryAfterFailure($document, $step, $prompt, $e);

            throw AiProviderFailureException::fromException(
                exception: $e,
                step: $step,
                provider: $config->provider,
                model: $config->model,
                timeout: $this->processingHistoryRecorder->isAiCallTimeout($e),
            );
        }
    }

    public function matchAccountId(
        AiProviderConfig $config,
        AiDocument $document,
        string $prompt,
        bool $promptChatHistoryEnabled,
    ): string {
        return $this->requestTextStep('account_matching', $config, $document, $prompt, $promptChatHistoryEnabled);
    }

    public function matchPayeeId(
        AiProviderConfig $config,
        AiDocument $document,
        string $prompt,
        bool $promptChatHistoryEnabled,
    ): string {
        return $this->requestTextStep('payee_matching', $config, $document, $prompt, $promptChatHistoryEnabled);
    }

    public function matchInvestmentId(
        AiProviderConfig $config,
        AiDocument $document,
        string $prompt,
        bool $promptChatHistoryEnabled,
    ): string {
        return $this->requestTextStep('investment_matching', $config, $document, $prompt, $promptChatHistoryEnabled);
    }

    protected function executeStructuredRequest(
        AiProviderConfig $config,
        AiDocument $document,
        string $prompt,
        bool $promptChatHistoryEnabled,
        ObjectSchema $schema,
    ): array {
        $pendingRequest = Prism::structured()
            ->using($config->provider, $config->model)
            ->usingProviderConfig([
                'api_key' => $config->api_key,
            ])
            ->withSchema($schema);

        if ($config->provider === 'openai') {
            $pendingRequest = $pendingRequest->withProviderOptions([
                'schema' => [
                    'strict' => true,
                ],
            ]);
        }

        $response = $promptChatHistoryEnabled
            ? $pendingRequest
                ->withMessages($this->aiPromptBuilder->buildPromptMessageChain($prompt, $document->ai_chat_history))
                ->asStructured()
            : $pendingRequest
                ->withPrompt($prompt)
                ->asStructured();

        return [
            'structured' => $response->structured,
            'text' => $response->text,
        ];
    }

    protected function executeTextRequest(
        AiProviderConfig $config,
        AiDocument $document,
        string $prompt,
        bool $promptChatHistoryEnabled,
    ): string {
        $pendingRequest = Prism::text()
            ->using($config->provider, $config->model)
            ->usingProviderConfig([
                'api_key' => $config->api_key,
            ]);

        $response = $promptChatHistoryEnabled
            ? $pendingRequest
                ->withMessages($this->aiPromptBuilder->buildPromptMessageChain($prompt, $document->ai_chat_history))
                ->asText()
            : $pendingRequest
                ->withPrompt($prompt)
                ->asText();

        return $response->text ?? '';
    }

    private function requestTextStep(
        string $step,
        AiProviderConfig $config,
        AiDocument $document,
        string $prompt,
        bool $promptChatHistoryEnabled,
    ): string {
        try {
            $textResponse = $this->executeTextRequest($config, $document, $prompt, $promptChatHistoryEnabled);
            $this->processingHistoryRecorder->appendProcessingHistory($document, $step, $prompt, $textResponse);

            return $textResponse;
        } catch (Exception $e) {
            $this->processingHistoryRecorder->appendAiFallbackHistoryAfterFailure($document, $step, $prompt, $e);

            throw AiProviderFailureException::fromException(
                exception: $e,
                step: $step,
                provider: $config->provider,
                model: $config->model,
                timeout: $this->processingHistoryRecorder->isAiCallTimeout($e),
            );
        }
    }

    private function buildMainExtractionSchema(): ObjectSchema
    {
        return new ObjectSchema(
            name: 'transaction_extraction',
            description: 'Extracted transaction payload from financial document.',
            properties: [
                new EnumSchema(
                    name: 'transaction_type',
                    description: 'Detected transaction type.',
                    options: [
                        'withdrawal',
                        'deposit',
                        'transfer',
                        'buy',
                        'sell',
                        'dividend',
                        'interest',
                        'add_shares',
                        'remove_shares',
                    ]
                ),
                new StringSchema('account', 'Account name for standard/investment transaction.', nullable: true),
                new StringSchema('account_from', 'Source account name for transfer.', nullable: true),
                new StringSchema('account_to', 'Destination account name for transfer.', nullable: true),
                new StringSchema('payee', 'Payee or merchant name for standard transaction.', nullable: true),
                new StringSchema('date', 'Transaction date in YYYY-MM-DD format.', nullable: true),
                new NumberSchema('amount', 'Transaction amount.', nullable: true),
                new StringSchema('currency', 'ISO currency code.', nullable: true),
                new ArraySchema(
                    name: 'transaction_items',
                    description: 'Line items for standard transaction receipts.',
                    items: new ObjectSchema(
                        name: 'transaction_item',
                        description: 'Single line item extracted from the document.',
                        properties: [
                            new StringSchema('description', 'Normalized item description.', nullable: true),
                            new NumberSchema('amount', 'Item amount.', nullable: true),
                        ],
                        requiredFields: ['description', 'amount']
                    ),
                    nullable: true
                ),
                new StringSchema('investment', 'Investment name, ticker, or ISIN.', nullable: true),
                new NumberSchema('quantity', 'Number of shares/units.', nullable: true),
                new NumberSchema('price', 'Price per share/unit.', nullable: true),
                new NumberSchema('commission', 'Commission or fee amount.', nullable: true),
                new NumberSchema('tax', 'Tax amount.', nullable: true),
                new NumberSchema('dividend', 'Dividend amount.', nullable: true),
            ],
            requiredFields: [
                'transaction_type',
                'account',
                'account_from',
                'account_to',
                'payee',
                'date',
                'amount',
                'currency',
                'transaction_items',
                'investment',
                'quantity',
                'price',
                'commission',
                'tax',
                'dividend',
            ]
        );
    }

    private function buildCategoryMatchingSchema(): ObjectSchema
    {
        return new ObjectSchema(
            name: 'category_matches',
            description: 'Category match results for transaction items.',
            properties: [
                new ArraySchema(
                    name: 'matches',
                    description: 'List of category match results, one per input item.',
                    items: new ObjectSchema(
                        name: 'category_match',
                        description: 'Category match result for a single transaction item.',
                        properties: [
                            new NumberSchema('item_index', 'Zero-based index of the matched item.'),
                            new NumberSchema('recommended_category_id', 'ID of the recommended category, or null if none.', nullable: true),
                            new NumberSchema('confidence_score', 'Confidence score between 0 and 1, or null if none.', nullable: true),
                        ],
                        requiredFields: ['item_index', 'recommended_category_id', 'confidence_score']
                    ),
                ),
            ],
            requiredFields: ['matches']
        );
    }
}
