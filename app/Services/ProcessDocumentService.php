<?php

namespace App\Services;

use App\Exceptions\OcrUnavailableException;
use App\Models\AiDocument;
use App\Models\AiProviderConfig;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\Storage;
use JsonException;
use Psr\Http\Client\ClientExceptionInterface;
use srmklive\Prism\Contracts\Provider;
use srmklive\Prism\Facades\Prism;
use Log;

class ProcessDocumentService
{
    public function __construct(
        private TextExtractionService $textExtractor,
        private AssetMatchingService $assetMatchingService,
        private CategoryLearningService $categoryLearningService
    ) {}

    /**
     * Process a document and extract transaction data
     *
     * @throws Exception
     */
    public function process(AiDocument $document): array
    {
        try {
            // Validate document state
            if ($document->status !== 'ready_for_processing') {
                throw new Exception('Document is not ready for processing');
            }

            // Get user and their provider config
            $user = $document->user;
            $config = $user->aiProviderConfigs()->first();

            if (! $config) {
                throw new Exception('No AI provider configured for user');
            }

            // Extract text from all files
            $extractedText = $this->extractTextFromFiles($document);

            if (empty($extractedText)) {
                throw new Exception('No text could be extracted from document files');
            }

            // Build AI prompt with normalized assets and category learning
            $prompt = $this->buildPrompt($user, $extractedText, $document->custom_prompt);

            // Call AI provider and get response
            $aiResponse = $this->callAiProvider($config, $prompt);

            // Validate and parse response
            $transactionData = $this->validateAndParseResponse($aiResponse);

            // Store processed data and update document
            $document->processed_transaction_data = $transactionData;
            $document->processed_at = now();
            $document->status = 'ready_for_review';
            $document->save();

            return [
                'success' => true,
                'transaction_data' => $transactionData,
            ];
        } catch (Exception $e) {
            $document->status = 'processing_failed';
            $document->save();

            throw $e;
        }
    }

    /**
     * Extract text from all files in the document
     */
    private function extractTextFromFiles(AiDocument $document): string
    {
        $texts = [];
        $visionConfig = $document->user->aiProviderConfigs()->first();

        foreach ($document->aiDocumentFiles as $file) {
            try {
                $fullPath = Storage::disk('local')->path($file->file_path);

                $text = $this->textExtractor->extractFromFile(
                    filePath: $fullPath,
                    fileType: $file->file_type,
                    visionConfig: $visionConfig
                );

                if ($text) {
                    $texts[] = $text;
                }
            } catch (OcrUnavailableException $e) {
                // OCR required but unavailable - log and continue
                Log::warning("OCR unavailable for {$file->file_path}: {$e->getMessage()}");
            } catch (Exception $e) {
                // Log error but continue with other files
                Log::warning("Failed to extract text from file {$file->file_path}: {$e->getMessage()}");
            }
        }

        return implode("\n\n---\n\n", $texts);
    }

    /**
     * Build the AI prompt with context from user assets and learnings
     */
    private function buildPrompt(User $user, string $extractedText, ?string $customPrompt = null): string
    {
        // Build asset lists for matching
        $accountsList = $this->assetMatchingService->formatAccountsForPrompt($user);
        $payeesList = $this->assetMatchingService->formatPayeesForPrompt($user);
        $investmentsList = $this->assetMatchingService->formatInvestmentsForPrompt($user);
        $categoryLearnings = $this->categoryLearningService->getLearningDataForPrompt($user);

        $systemPrompt = $this->getSystemPrompt();

        if ($customPrompt) {
            $systemPrompt .= "\n\nCustom instructions from user:\n{$customPrompt}";
        }

        $userPrompt = <<<EOF
# Document Content
{$extractedText}

# Available Accounts (ID: Name|Aliases)
{$accountsList}

# Available Payees (ID: Name)
{$payeesList}

# Available Investments (ID: Name|Code|ISIN)
{$investmentsList}

# Category Learning (item description → category ID)
{$categoryLearnings}

Please extract the transaction data from the document and return a valid JSON response matching the specified schema.
EOF;

        return $userPrompt;
    }

    /**
     * Get the main system prompt for transaction extraction
     */
    private function getSystemPrompt(): string
    {
        return <<<'EOF'
You are a financial document processor. Your task is to extract transaction information from documents (receipts, invoices, emails, etc.) and return structured JSON data.

Return ONLY valid JSON, no additional text or explanation.

The response must match this exact schema:
{
  "raw": {
    "payee": "string|null - the identified payee/merchant name",
    "account": "string|null - the identified account name",
    "date": "string|null - the identified transaction date",
    "amount": "number|null - the identified transaction amount",
    "currency": "string|null - the ISO currency code",
    "transaction_type": "string|null - one of 'deposit', 'withdrawal', 'transfer', 'buy', 'sell', 'dividend', 'interest', 'add_shares', 'remove_shares'"
  },
  "date": "YYYY-MM-DD|null - the transaction date",
  "config_type": "standard|investment - based on transaction type",
  "transaction_type_id": "number|null - the ID from the available transaction types",
  "config": {
    "amount_to": "number - for standard transactions",
    "account_to_id": "number|null - for standard transactions (if transfer/deposit)",
    "account_from_id": "number|null - for standard transactions (if withdrawal/transfer)",
    "payee_id": "number|null - for standard withdrawals",
    "account_id": "number|null - for investment transactions",
    "investment_id": "number|null - for investment transactions",
    "quantity": "number|null - for investment transactions",
    "price": "number|null - for investment transactions",
    "dividend": "number|null - for dividend/interest transactions"
  },
  "items": [
    {
      "amount": "number - line item amount",
      "category_id": "number|null - category ID from category learning"
    }
  ]
}

Rules:
- For multi-item receipts, create separate items in the "items" array
- If only one amount is identified, create one item with that amount
- Always use the matched IDs from the provided lists
- If an asset cannot be matched, set its ID to null
- Set all null fields to null, never omit them
- For investments, sum quantities/prices if multiple items
- Date should be in YYYY-MM-DD format or null
- Amount should be a number without currency symbol
EOF;
    }

    /**
     * Call the AI provider via Prism
     *
     * @throws ClientExceptionInterface
     */
    private function callAiProvider(AiProviderConfig $config, string $prompt): string
    {
        $provider = Prism::provider($config->provider);

        $response = $provider
            ->usingModel($config->model)
            ->withSystemPrompt($this->getSystemPrompt())
            ->withTemperature(config('ai-documents.processing.ai_temperature'))
            ->withTopP(config('ai-documents.processing.ai_top_p'))
            ->withFrequencyPenalty(config('ai-documents.processing.ai_frequency_penalty'))
            ->withPresencePenalty(config('ai-documents.processing.ai_presence_penalty'))
            ->complete($prompt);

        return $response['content'] ?? '';
    }

    /**
     * Validate and parse AI response
     *
     * @throws JsonException
     */
    private function validateAndParseResponse(string $response): array
    {
        // Try to extract JSON from the response
        $json = $this->extractJsonFromResponse($response);

        if (! $json) {
            throw new Exception('No JSON found in AI response');
        }

        $data = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

        // Validate required schema
        $this->validateSchema($data);

        return $data;
    }

    /**
     * Extract JSON from response (handles markdown code blocks)
     */
    private function extractJsonFromResponse(string $response): ?string
    {
        // Try to extract from markdown code block first
        if (preg_match('/```(?:json)?\s*\n?(.*?)\n?```/s', $response, $matches)) {
            return $matches[1];
        }

        // Try to find JSON object
        if (preg_match('/\{.*\}/s', $response, $matches)) {
            return $matches[0];
        }

        // Return as-is if it looks like JSON
        if (str_starts_with(trim($response), '{')) {
            return $response;
        }

        return null;
    }

    /**
     * Validate the response schema
     */
    private function validateSchema(array $data): void
    {
        $required = ['raw', 'date', 'config_type', 'transaction_type_id', 'config', 'items'];

        foreach ($required as $field) {
            if (! isset($data[$field])) {
                throw new Exception("Missing required field in AI response: {$field}");
            }
        }

        // Validate config_type
        if (! in_array($data['config_type'], ['standard', 'investment'])) {
            throw new Exception('Invalid config_type: must be standard or investment');
        }

        // Validate config structure based on type
        if ($data['config_type'] === 'standard') {
            if (! isset($data['config']['amount_to'])) {
                throw new Exception('Missing amount_to in standard transaction config');
            }
        }

        // Ensure items is array
        if (! is_array($data['items'])) {
            throw new Exception('Items must be an array');
        }
    }
}
