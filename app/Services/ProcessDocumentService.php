<?php

namespace App\Services;

use App\Enums\TransactionType as TransactionTypeEnum;
use App\Exceptions\OcrUnavailableException;
use App\Models\AiDocument;
use App\Models\AiProviderConfig;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use JsonException;

class ProcessDocumentService
{
    public function __construct(
        private TextExtractionService $textExtractor,
        private AssetMatchingService $assetMatchingService,
        private CategoryLearningService $categoryLearningService
    ) {
    }

    /**
     * Process a document and extract transaction data
     *
     * @throws Exception
     */
    public function process(AiDocument $document): array
    {
        try {
            // Update status to processing
            $document->status = 'processing';
            $document->save();

            // Get user and their AI provider config - currently only one config is allowed
            $user = $document->user;
            $config = $user->aiProviderConfigs()->first();

            if (! $config) {
                throw new Exception('No AI provider configured for user');
            }

            // Step 1: Extract text from all files
            $extractedText = $this->extractTextFromFiles($document, $config);

            if (empty($extractedText)) {
                throw new Exception('No text could be extracted from document files');
            }

            // Step 2: Extract core transaction data
            $rawData = $this->extractTransactionData($config, $extractedText, $document->custom_prompt);

            Log::debug('AI extracted raw transaction data', ['raw_data' => $rawData]);

            // Step 3: Determine transaction type
            $transactionType = Str::lower($rawData['transaction_type'] ?? 'withdrawal');

            // Step 4: Match assets based on transaction type
            $accountId = null;
            $accountFromId = null;
            $accountToId = null;
            $investmentId = null;

            if (in_array($transactionType, TransactionTypeEnum::investmentTypes())) {
                // Investment transaction: match account and investment
                if (!empty($rawData['account'])) {
                    $accountId = $this->matchAccount($config, $user, $rawData['account']);
                }
                if (!empty($rawData['investment'])) {
                    $investmentId = $this->matchInvestment($config, $user, $rawData['investment']);
                }
            } elseif ($transactionType === 'transfer') {
                // Transfer: match two accounts
                if (!empty($rawData['account_from'])) {
                    $accountFromId = $this->matchAccount($config, $user, $rawData['account_from']);
                }
                if (!empty($rawData['account_to'])) {
                    $accountToId = $this->matchAccount($config, $user, $rawData['account_to']);
                }
            } elseif ($transactionType === 'withdrawal') {
                // Withdrawal: match account (from) and payee (to)
                if (!empty($rawData['account'])) {
                    $accountFromId = $this->matchAccount($config, $user, $rawData['account']);
                }
                if (!empty($rawData['payee'])) {
                    $accountToId = $this->matchPayee($config, $user, $rawData['payee']);
                }
            } elseif ($transactionType === 'deposit') {
                // Deposit: match payee (from) and account (to)
                if (!empty($rawData['payee'])) {
                    $accountFromId = $this->matchPayee($config, $user, $rawData['payee']);
                }
                if (!empty($rawData['account'])) {
                    $accountToId = $this->matchAccount($config, $user, $rawData['account']);
                }
            }

            // Step 4: Build final transaction data structure
            $transactionData = $this->buildTransactionData(
                $rawData,
                $transactionType,
                $accountId,
                $accountFromId,
                $accountToId,
                $investmentId,
                $user
            );

            // Step 5: Store processed data and update document
            $document->processed_transaction_data = $transactionData;
            $document->processed_at = now();
            $document->status = 'ready_for_review';
            $document->save();

            Log::info("Document {$document->id} processed successfully");

            return [
                'success' => true,
                'transaction_data' => $transactionData,
            ];
        } catch (Exception $e) {
            Log::error("Document {$document->id} processing failed: {$e->getMessage()}");

            $document->status = 'processing_failed';
            $document->save();

            throw $e;
        }
    }

    /**
     * Extract text from all files in the document
     */
    private function extractTextFromFiles(AiDocument $document, AiProviderConfig $config): string
    {
        $texts = [];

        foreach ($document->aiDocumentFiles as $file) {
            try {
                $text = $this->textExtractor->extractFromFile(
                    filePath: $file->file_path,
                    fileType: $file->file_type,
                    visionConfig: $config
                );

                if ($text) {
                    $texts[] = $text;
                }
            } catch (OcrUnavailableException $e) {
                Log::warning("OCR unavailable for {$file->file_path}: {$e->getMessage()}");
                throw $e;
            } catch (Exception $e) {
                Log::warning("Failed to extract text from file {$file->file_path}: {$e->getMessage()}");
            }
        }

        return implode("\n\n---\n\n", $texts);
    }

    /**
     * Extract basic transaction data from text using AI
     */
    private function extractTransactionData(AiProviderConfig $config, string $text, ?string $customPrompt = null): array
    {
        $prompt = $this->buildMainExtractionPrompt($text, $customPrompt);

        $response = $this->callAi($config, $prompt);

        try {
            $data = json_decode($response, true, 512, JSON_THROW_ON_ERROR);

            Log::debug('Parsed main AI response', ['data' => $data]);

            return $data;
        } catch (JsonException $e) {
            Log::error('Failed to parse main AI response', [
                'response' => $response,
                'error' => $e->getMessage(),
            ]);

            throw new Exception('Failed to parse main AI response as JSON');
        }
    }

    /**
     * Match account name to user's accounts
     */
    private function matchAccount(AiProviderConfig $config, User $user, string $accountName): ?int
    {
        // Create service instance with user context
        $matchingService = new AssetMatchingService($user);

        // Get similar accounts - this already limits to top N matches
        $similarAccounts = $matchingService->matchAccounts($accountName);

        if (empty($similarAccounts)) {
            Log::debug('No similar accounts found', ['account_name' => $accountName]);

            return null;
        }

        // Format for AI prompt
        $accountsList = collect($similarAccounts)
            ->map(fn ($match) => "{$match['id']}: {$match['name']}")
            ->join("\n");

        $prompt = <<<EOF
I will provide you a list of accounts and their IDs in the following format: "ID: Account name (optional aliases)"
I'd like you to identify the ID of the account mentioned in the document.
Please provide ONLY the numeric ID, or N/A if there is no match.

The list of accounts is:
{$accountsList}

The account mentioned in the document is: {$accountName}
EOF;

        $response = $this->callAi($config, $prompt);

        $result = mb_trim($response);

        Log::debug('Account matching AI response', [
            'prompt' => $prompt,
            'account_list' => $accountsList,
            'result' => $result
        ]);

        return $result !== 'N/A' && is_numeric($result) ? (int) $result : null;
    }

    /**
     * Match payee name to user's payees
     */
    private function matchPayee(AiProviderConfig $config, User $user, string $payeeName): ?int
    {
        // Create service instance with user context
        $matchingService = new AssetMatchingService($user);

        // Get similar payees
        $similarPayees = $matchingService->matchPayees($payeeName);

        if (empty($similarPayees)) {
            Log::debug('No similar payees found', ['payee_name' => $payeeName]);

            return null;
        }

        // Take top 10 matches
        $topMatches = array_slice($similarPayees, 0, 10);

        // Format for AI prompt
        $payeesList = collect($topMatches)
            ->map(fn ($match) => "{$match['id']}: {$match['name']}")
            ->join("\n");

        $prompt = <<<EOF
I will provide you a list of payees and their IDs in the following format: "ID: Payee name (optional aliases)"
I'd like you to identify the ID of the payee mentioned in the document.
Please provide ONLY the numeric ID, or N/A if there is no match.

The list of payees is:
{$payeesList}

The payee mentioned in the document is: {$payeeName}
EOF;

        $response = $this->callAi($config, $prompt);

        $result = mb_trim($response);

        Log::debug('Payee matching AI response', [
            'prompt' => $prompt,
            'payee_list' => $payeesList,
            'result' => $result
        ]);

        return $result !== 'N/A' && is_numeric($result) ? (int) $result : null;
    }

    /**
     * Match investment name to user's investments
     */
    private function matchInvestment(AiProviderConfig $config, User $user, string $investmentName): ?int
    {
        // Create service instance with user context
        $matchingService = new AssetMatchingService($user);

        // Get similar investments
        $similarInvestments = $matchingService->matchInvestments($investmentName);

        if (empty($similarInvestments)) {
            Log::debug('No similar investments found', ['investment_name' => $investmentName]);

            return null;
        }

        // Take top 10 matches
        $topMatches = array_slice($similarInvestments, 0, 10);

        // Format for AI prompt
        $investmentsList = collect($topMatches)
            ->map(fn ($match) => "{$match['id']}: {$match['name']}")
            ->join("\n");

        $prompt = <<<EOF
I will provide you a list of investments and their IDs in the following format: "ID: Investment name (optional symbol and ISIN)"
I'd like you to identify the ID of the investment mentioned in the document.
Please provide ONLY the numeric ID, or N/A if there is no match.

The list of investments is:
{$investmentsList}

The investment mentioned in the document is: {$investmentName}
EOF;

        $response = $this->callAi($config, $prompt);

        $result = mb_trim($response);

        Log::debug('Investment matching AI response', [
            'prompt' => $prompt,
            'investment_list' => $investmentsList,
            'result' => $result
        ]);

        return $result !== 'N/A' && is_numeric($result) ? (int) $result : null;
    }

    /**
     * Build final transaction data structure
     */
    private function buildTransactionData(
        array $rawData,
        string $transactionType,
        ?int $accountId,
        ?int $accountFromId,
        ?int $accountToId,
        ?int $investmentId,
        User $user
    ): array {
        $isInvestment = in_array($transactionType, TransactionTypeEnum::investmentTypes());

        $data = [
            'raw' => $rawData,
            'date' => $rawData['date'] ?? now()->format('Y-m-d'),
            'config_type' => $isInvestment ? 'investment' : 'standard',
            'transaction_type' => $transactionType,
            'config' => [],
            'items' => [],
        ];

        // Build config based on transaction type
        if ($isInvestment) {
            $data['config'] = [
                'account_id' => $accountId,
                'investment_id' => $investmentId,
                'quantity' => in_array($transactionType, TransactionTypeEnum::investmentTypesWithQuantity()) ? $rawData['quantity'] : null,
                'price' => in_array($transactionType, TransactionTypeEnum::investmentTypesWithPrice()) ? $rawData['price'] : null,
                'commission' => $rawData['commission'] ?? null,
                'tax' => $rawData['tax'] ?? null,
                'dividend' => in_array($transactionType, [TransactionTypeEnum::DIVIDEND->value, TransactionTypeEnum::INTEREST_YIELD->value]) ? $rawData['amount'] : null,
            ];
        } else {
            $amount = floatval($rawData['amount'] ?? 0);

            // Config format is the same for withdrawal, deposit, and transfer
            $data['config'] = [
                'amount_from' => $amount,
                'amount_to' => $amount,
                'account_from_id' => $accountFromId,
                'account_to_id' => $accountToId,
            ];

        }

        // Build items array with category learning
        if (! $isInvestment && isset($rawData['items']) && is_array($rawData['items'])) {
            foreach ($rawData['items'] as $item) {
                $categoryId = $this->matchCategoryForItem($item['description'] ?? '', $user);

                $data['items'][] = [
                    'amount' => floatval($item['amount'] ?? 0),
                    'category_id' => $categoryId,
                    'description' => $item['description'] ?? '',
                ];
            }
        } else {
            // Single item transaction
            $amount = floatval($rawData['amount'] ?? 0);
            if ($amount > 0) {
                $data['items'][] = [
                    'amount' => $amount,
                    'category_id' => null,
                    'description' => $rawData['payee'] ?? '',
                ];
            }
        }

        return $data;
    }

    /**
     * Match category for an item based on learning data
     */
    private function matchCategoryForItem(string $description, User $user): ?int
    {
        if (empty($description)) {
            return null;
        }

        // Create service instance with user context
        $learningService = new CategoryLearningService($user);

        $normalized = $learningService->normalize($description);

        // Try to find exact match in learning data
        $learning = $user->categoryLearning()
            ->where('item_description', $normalized)
            ->orderByDesc('usage_count')
            ->first();

        return $learning?->category_id;
    }

    /**
     * Build the main extraction prompt
     */
    private function buildMainExtractionPrompt(string $text, ?string $customPrompt = null): string
    {
        $customInstructions = $customPrompt ? "Custom instructions from user:\n{$customPrompt}\n\n" : '';

        $prompt = <<<EOF
I will provide you the text content of a financial document (receipt, invoice, email, bank statement, brokerage confirmation, etc.).
The language used may vary.
I'd like you to extract transaction information from it.
The response must be in JSON format, without any additional text, explanation, or markdown code blocks.

The document can represent either a STANDARD transaction or an INVESTMENT transaction.

FOR STANDARD TRANSACTIONS (purchases, deposits, transfers):
{
  "transaction_type": "withdrawal|deposit|transfer",
  "account": "name of the account/card (for withdrawal/deposit)",
  "account_from": "source account name (for transfer only)",
  "account_to": "destination account name (for transfer only)",
  "payee": "merchant/payee name (for withdrawal/deposit)",
  "date": "yyyy-mm-dd format",
  "amount": "total amount as number, no currency symbol",
  "currency": "ISO code (USD, EUR, etc.) if available; not fundamental for processing",
  "items": [
    {
      "description": "item description",
      "amount": "item monetary amount as number"
    }
  ]
}

FOR INVESTMENT TRANSACTIONS (stock/fund purchases, sales, dividends):
{
  "transaction_type": "exactly one of buy|sell|dividend|interest|add_shares|remove_shares",
  "account": "name of the brokerage/investment account",
  "investment": "name, ticker symbol or ISIN number of the stock/fund/security; ISIN or ticker is preferred if available",
  "date": "yyyy-mm-dd format",
  "amount": "total transaction amount (for dividend/interest)",
  "quantity": "number of shares/units (for buy/sell/add/remove)",
  "price": "price per share/unit (for buy/sell)",
  "commission": "total commission/fee amount as number, if available",
  "tax": "total tax amount as number, if available",
  "dividend": "dividend amount as number (for dividend/interest)",
  "currency": "ISO code (USD, EUR, etc.) if available; not fundamental for processing"
}

RULES:
* All keys listed for the detected transaction type are REQUIRED. Set to null if not available.
* Do NOT include keys from the other transaction type (e.g., don't include "quantity" for a withdrawal).
* transaction_type determines which schema to use:
  - withdrawal/deposit/transfer → standard transaction
  - buy/sell/dividend/interest/add_shares/remove_shares → investment transaction
* For transfers, extract BOTH account_from and account_to names
* For transfers, the items array must be empty (it is not supported to have line items on transfers)
* For receipts with multiple line items, extract each item separately in the items array
* For investment transactions, omit the "items" array (as it is not part of the sample schema anyway)
* Date format must be yyyy-mm-dd, use today's date if not specified

{$customInstructions}

The document content to process is:
"""
{$text}
"""

Return ONLY the JSON response, no other text or formatting.
EOF;

        return $prompt;
    }

    /**
     * Call AI provider and get text response
     */
    private function callAi(AiProviderConfig $config, string $prompt): string
    {
        try {
            $response = \Prism\Prism\Facades\Prism::text()
                ->using($config->provider, $config->model)
                ->usingProviderConfig([
                    'api_key' => $config->api_key,
                ])
                ->withPrompt($prompt)
                ->asText();

            return $response->text ?? '';
        } catch (Exception $e) {
            Log::error('AI provider call failed', [
                'provider' => $config->provider,
                'model' => $config->model,
                'error' => $e->getMessage(),
            ]);

            throw new Exception("AI provider error: {$e->getMessage()}");
        }
    }
}
