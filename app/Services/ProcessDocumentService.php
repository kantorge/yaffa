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
use Carbon\Carbon;

class ProcessDocumentService
{
    const SIMILARITY_THRESHOLD_TO_ACCEPT_MATCH = 0.95;

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
            // Get user and their AI provider config - currently only one config is allowed
            $user = $document->user;
            $config = $user->aiProviderConfigs()->first();

            if (! $config) {
                throw new Exception('No AI provider configured for user');
            }

            // Update status to processing
            $document->status = 'processing';
            $document->save();

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

            if (in_array($transactionType, TransactionTypeEnum::investmentTypeValues())) {
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
                $user,
                $config
            );

            // Step 5: Store processed data and update document
            $document->processed_transaction_data = $transactionData;
            $document->processed_at = Carbon::now();
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

            throw $e;
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

        // If the top match has high confidence, return it immediately to save AI calls
        $topMatch = reset($similarAccounts);
        if ($topMatch['similarity'] >= self::SIMILARITY_THRESHOLD_TO_ACCEPT_MATCH) {
            Log::info('High confidence match found for account name, skipping AI call', [
                'account_name' => $accountName,
                'account_name_in_list' => $topMatch['name'],
                'matched_id' => $topMatch['id'],
                'similarity' => $topMatch['similarity'],
            ]);

            return $topMatch['id'];
        }

        // Format for AI prompt
        $accountsList = collect($similarAccounts)
            ->map(fn ($match) => "{$match['id']}: {$match['name']}")
            ->join("\n");

        $prompt = <<<EOF
I will provide you a list of accounts and their IDs in the following format: "ID: Account name (optional aliases)"
I'd like you to identify the ID of the account mentioned in the document.
Please provide ONLY the numeric ID, or N/A if there is no match.

Primarily look for a match in the main name part, but also check the aliases if the main name doesn't match. The matching can be case insensitive.
If there's no exact match, try to find the closest one based on similarity.

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

        // Get similar payees - service limits to top N matches
        $similarPayees = $matchingService->matchPayees($payeeName);

        if (empty($similarPayees)) {
            Log::debug('No similar payees found', ['payee_name' => $payeeName]);

            return null;
        }

        // If the top match has high confidence, return it immediately to save AI calls
        $topMatch = reset($similarPayees);
        if ($topMatch['similarity'] >= self::SIMILARITY_THRESHOLD_TO_ACCEPT_MATCH) {
            Log::info('High confidence match found for payee name, skipping AI call', [
                'payee_name' => $payeeName,
                'payee_name_in_list' => $topMatch['name'],
                'matched_id' => $topMatch['id'],
                'similarity' => $topMatch['similarity'],
            ]);

            return $topMatch['id'];
        }

        // Format for AI prompt
        $payeesList = collect($similarPayees)
            ->map(fn ($match) => "{$match['id']}: {$match['name']}")
            ->join("\n");

        $prompt = <<<EOF
I will provide you a list of payees and their IDs in the following format: "ID: Payee name (optional aliases)"
I'd like you to identify the ID of the payee mentioned in the document.
Primarily look for a match in the main name part, but also check the aliases if the main name doesn't match. The matching can be case insensitive.
If there's no exact match, try to find the closest one based on similarity.

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

        // Get similar investments - this already limits to top N matches
        $similarInvestments = $matchingService->matchInvestments($investmentName);

        if (empty($similarInvestments)) {
            Log::debug('No similar investments found', ['investment_name' => $investmentName]);

            return null;
        }

        // If the top match has high confidence, return it immediately to save AI calls
        $topMatch = reset($similarInvestments);
        if ($topMatch['similarity'] >= self::SIMILARITY_THRESHOLD_TO_ACCEPT_MATCH) {
            Log::info('High confidence match found for investment name, skipping AI call', [
                'investment_name' => $investmentName,
                'investment_name_in_list' => $topMatch['name'],
                'matched_id' => $topMatch['id'],
                'similarity' => $topMatch['similarity'],
            ]);

            return $topMatch['id'];
        }

        // Format for AI prompt
        $investmentsList = collect($similarInvestments)
            ->map(fn ($match) => "{$match['id']}: {$match['name']}")
            ->join("\n");

        $prompt = <<<EOF
I will provide you a list of investments and their IDs in the following format: "ID: Investment name (optional symbol and ISIN)"
I'd like you to identify the ID of the investment mentioned in the document.
Either look for and EXACT symbol/ISIN match in the part within the parenthesis, or a name match in the main name part. The matching can be case insensitive, and might not be exact for the name.
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
        User $user,
        AiProviderConfig $config
    ): array {
        $isInvestment = in_array($transactionType, TransactionTypeEnum::investmentTypeValues());

        $data = [
            'raw' => $rawData,
            'date' => $rawData['date'] ?? now()->format('Y-m-d'),
            'config_type' => $isInvestment ? 'investment' : 'standard',
            'transaction_type' => $transactionType,
            'config' => [],
        ];

        // Build config based on transaction type
        if ($isInvestment) {
            $data['config'] = [
                'account_id' => $accountId,
                'investment_id' => $investmentId,
                'quantity' => in_array($transactionType, TransactionTypeEnum::investmentTypesWithQuantityValues()) ? $rawData['quantity'] : null,
                'price' => in_array($transactionType, TransactionTypeEnum::investmentTypesWithPriceValues()) ? $rawData['price'] : null,
                'commission' => $rawData['commission'] ?? null,
                'tax' => $rawData['tax'] ?? null,
                'dividend' => in_array($transactionType, [TransactionTypeEnum::DIVIDEND->value, TransactionTypeEnum::INTEREST_YIELD->value]) ? $rawData['amount'] : null,
            ];
        } else {
            // Will be populated later, either from AI items or single item based on amount
            $data['transaction_items'] = [];

            $amount = floatval($rawData['amount'] ?? 0);

            // Config format is the same for withdrawal, deposit, and transfer
            $data['config'] = [
                'amount_from' => $amount,
                'amount_to' => $amount,
                'account_from_id' => $accountFromId,
                'account_to_id' => $accountToId,
            ];

        }

        // Build transaction_items array with category learning (batch AI matching)
        if (! $isInvestment && isset($rawData['transaction_items']) && is_array($rawData['transaction_items'])) {
            $data['transaction_items'] = $this->matchCategoriesForItems($rawData['transaction_items'], $user, $config);
        }

        return $data;
    }

    /**
     * Check for exact category match in learning data (local only)
     *
     * @return array{recommended_category_id: int, match_type: string, confidence_score: float}|null
     */
    private function checkExactCategoryMatch(string $description, User $user): ?array
    {
        if (empty($description)) {
            return null;
        }

        // Create service instance with user context
        $learningService = new CategoryLearningService($user);

        $normalized = $learningService->normalize($description);

        // Try to find exact match in learning data with active category
        $learning = $user->categoryLearning()
            ->where('item_description', $normalized)
            ->whereHas('category', fn ($q) => $q->where('active', 1))
            ->orderByDesc('usage_count')
            ->first();

        if ($learning) {
            return [
                'recommended_category_id' => $learning->category_id,
                'match_type' => 'exact',
                'confidence_score' => 1.0,
            ];
        }

        return null;
    }

    /**
     * Match categories for multiple items (batch processing with AI)
     *
     * @param  array  $items  Raw items from AI extraction
     * @return array Enriched items with recommended_category_id, match_type, confidence_score
     */
    protected function matchCategoriesForItems(array $items, User $user, AiProviderConfig $config): array
    {
        $enrichedItems = [];
        $itemsNeedingAi = [];

        // First pass: Check for exact matches
        foreach ($items as $index => $item) {
            $description = $item['description'] ?? '';
            $amount = floatval($item['amount'] ?? 0);

            $exactMatch = $this->checkExactCategoryMatch($description, $user);

            if ($exactMatch) {
                // Exact match found locally
                $enrichedItems[$index] = [
                    'amount' => $amount,
                    'description' => $description,
                    'recommended_category_id' => $exactMatch['recommended_category_id'],
                    'match_type' => $exactMatch['match_type'],
                    'confidence_score' => $exactMatch['confidence_score'],
                ];
            } else {
                // No exact match, will need AI
                $itemsNeedingAi[$index] = [
                    'amount' => $amount,
                    'description' => $description,
                ];
                // Placeholder for now
                $enrichedItems[$index] = [
                    'amount' => $amount,
                    'description' => $description,
                    'recommended_category_id' => null,
                    'match_type' => null,
                    'confidence_score' => null,
                ];
            }
        }

        // If all items had exact matches, return early (no AI call needed)
        if (empty($itemsNeedingAi)) {
            Log::debug('All items matched exactly, no AI call needed', [
                'item_count' => count($enrichedItems),
            ]);

            return array_values($enrichedItems);
        }

        // Second pass: AI matching for items without exact matches
        try {
            $aiMatches = $this->matchCategoriesBatch($itemsNeedingAi, $user, $config);

            // Merge AI results back into enriched items
            // All recommendations (exact or AI) populate recommended_category_id
            foreach ($aiMatches as $index => $aiMatch) {
                if (isset($enrichedItems[$index])) {
                    $enrichedItems[$index]['recommended_category_id'] = $aiMatch['recommended_category_id'];
                    $enrichedItems[$index]['match_type'] = $aiMatch['match_type'];
                    $enrichedItems[$index]['confidence_score'] = $aiMatch['confidence_score'];
                }
            }
        } catch (Exception $e) {
            Log::error('Category batch matching failed, items will have no category', [
                'error' => $e->getMessage(),
                'item_count' => count($itemsNeedingAi),
            ]);
            // Items already have null recommended_category_id, so no changes needed
        }

        return array_values($enrichedItems);
    }

    /**
     * Match categories using AI for multiple items in a single batch call
     *
     * @param  array  $items  Items needing AI matching (indexed by original position)
     * @return array AI match results indexed by original position
     */
    protected function matchCategoriesBatch(array $items, User $user, AiProviderConfig $config): array
    {
        if (empty($items)) {
            return [];
        }

        $matchingService = new AssetMatchingService($user);

        // Gather similar learning records for context (per item)
        $learningContext = [];
        foreach ($items as $index => $item) {
            $similarLearning = $matchingService->matchCategoryLearning($item['description']);
            if (! empty($similarLearning)) {
                $learningContext[$index] = $similarLearning;
            }
        }

        // Build AI prompt with all items
        $prompt = $this->buildCategoryMatchingPrompt(
            $items,
            $learningContext,
            $matchingService->formatCategoriesForPrompt($user)
        );

        $response = $this->callAi($config, $prompt);

        // Parse AI response
        try {
            $aiResults = json_decode($response, true, 512, JSON_THROW_ON_ERROR);

            Log::debug('Parsed category matching AI response', ['results' => $aiResults]);

            // Validate and format results
            $matches = [];
            foreach ($aiResults as $result) {
                $itemIndex = $result['item_index'] ?? null;
                $categoryId = $result['category_id'] ?? null;
                $confidenceScore = $result['confidence_score'] ?? null;

                if ($itemIndex === null || ! isset($items[$itemIndex])) {
                    continue;
                }

                // Validate category exists and is active
                if ($categoryId !== null) {
                    $categoryExists = $user->categories()
                        ->active()
                        ->where('id', $categoryId)
                        ->exists();

                    if (! $categoryExists) {
                        Log::warning('AI suggested invalid/inactive category', [
                            'recommended_category_id' => $categoryId,
                            'item_index' => $itemIndex,
                        ]);
                        $categoryId = null;
                        $confidenceScore = null;
                    }
                }

                $matches[$itemIndex] = [
                    'recommended_category_id' => $categoryId,
                    'match_type' => $categoryId !== null ? 'ai' : null,
                    'confidence_score' => $confidenceScore,
                ];
            }

            return $matches;
        } catch (JsonException $e) {
            Log::error('Failed to parse category matching AI response', [
                'response' => $response,
                'error' => $e->getMessage(),
            ]);

            throw new Exception('Failed to parse category matching AI response as JSON');
        }
    }

    /**
     * Build AI prompt for batch category matching
     */
    private function buildCategoryMatchingPrompt(
        array $items,
        array $learningContext,
        string $categoriesList
    ): string {
        // Format learning context if available
        $learningSection = '';
        if (! empty($learningContext)) {
            $learningLines = [];
            foreach ($learningContext as $index => $learningRecords) {
                $learningLines[] = "Item {$index} similar patterns:";
                foreach ($learningRecords as $record) {
                    $learningLines[] = "  - Recommended Category {$record['recommended_category_id']}: {$record['description']} (similarity: {$record['similarity']})";
                }
            }
            $learningSection = "CATEGORY LEARNING PATTERNS (past transaction descriptions):\n" . implode("\n", $learningLines) . "\n\n";
        }

        // Format items list
        $itemsLines = [];
        foreach ($items as $index => $item) {
            $itemsLines[] = "[{$index}] {$item['description']} - \${$item['amount']}";
        }
        $itemsList = implode("\n", $itemsLines);

        $prompt = <<<EOF
You will be provided with:
1. Category learning patterns (past transaction descriptions matched to categories) - if available
2. Full list of active categories available for this user
3. Multiple line items from a receipt that need category assignment

Your task: Match each line item to the most appropriate category.

RULES:
- Prioritize learning patterns if item description closely matches past patterns
- Use category list to find best semantic match if no learning patterns match
- Return confidence score 0.0-1.0 for each match (1.0 = certain, <0.5 = uncertain)
- Return recommended_category_id as null if no reasonable match exists (confidence too low or no semantic match)
- IMPORTANT: item_index must match the index shown in square brackets [N] in LINE ITEMS list

{$learningSection}AVAILABLE ACTIVE CATEGORIES:
{$categoriesList}

LINE ITEMS TO MATCH:
{$itemsList}

Return JSON array ONLY (no markdown, no explanation, no code blocks):
[
  {"item_index": 0, "recommended_category_id": 123, "confidence_score": 0.95},
  {"item_index": 1, "recommended_category_id": null, "confidence_score": null}
]
EOF;

        return $prompt;
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

FOR STANDARD TRANSACTIONS (spend, purchase, gain money, transfer between accounts):
{
  "transaction_type": "withdrawal|deposit|transfer",
  "account": "name of the account/card (for withdrawal/deposit)",
  "account_from": "source account name (for transfer only)",
  "account_to": "destination account name (for transfer only)",
  "payee": "merchant/payee name (for withdrawal/deposit)",
  "date": "yyyy-mm-dd format",
  "amount": "total amount as number, no currency symbol",
  "currency": "ISO code (USD, EUR, etc.) if available; not fundamental for processing",
  "transaction_items": [
    {
      "description": "item description",
      "amount": "item monetary amount as number"
    }
  ]
}

FOR INVESTMENT TRANSACTIONS (stock/fund buy, sales, dividends):
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
* For transfers, the transaction_items array must be empty (it is not supported to have line items on transfers)
* For receipts with multiple line items, extract each item separately into the transaction_items array
* For investment transactions, omit the "transaction_items" array (as it is not part of the sample schema anyway)
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
    protected function callAi(AiProviderConfig $config, string $prompt): string
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
