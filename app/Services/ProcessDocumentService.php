<?php

namespace App\Services;

use App\Enums\TransactionType as TransactionTypeEnum;
use App\Exceptions\AiResponseParseException;
use App\Exceptions\InvalidAiResponseSchemaException;
use App\Exceptions\OcrUnavailableException;
use App\Models\AiDocument;
use App\Models\AiProviderConfig;
use App\Models\AccountEntity;
use App\Models\CategoryLearning;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ProcessDocumentService
{
    public const SIMILARITY_THRESHOLD_TO_ACCEPT_MATCH = 0.95;

    private AiUserSettingsResolver $aiUserSettingsResolver;

    private AiStepGateway $aiStepGateway;

    private ProcessingHistoryRecorder $processingHistoryRecorder;

    private bool $promptChatHistoryEnabled = true;

    public function __construct(
        private TextExtractionService $textExtractor,
        private CategoryLearningService $categoryLearningService,
        private PayeeCategoryStatsService $payeeCategoryStatsService,
        private AiExtractionSchemaValidator $aiExtractionSchemaValidator,
        private AiPromptBuilder $aiPromptBuilder,
        ?AiUserSettingsResolver $aiUserSettingsResolver = null,
        ?AiStepGateway $aiStepGateway = null,
        ?ProcessingHistoryRecorder $processingHistoryRecorder = null,
    ) {
        $this->aiUserSettingsResolver = $aiUserSettingsResolver ?? app(AiUserSettingsResolver::class);
        $this->processingHistoryRecorder = $processingHistoryRecorder ?? app(ProcessingHistoryRecorder::class);
        $this->aiStepGateway = $aiStepGateway ?? app(AiStepGateway::class);
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

            $resolvedSettings = $this->resolveAiUserSettings($user);
            $this->promptChatHistoryEnabled = (bool) ($resolvedSettings['prompt_chat_history_enabled'] ?? true);
            $autoAcceptThreshold = (float) ($resolvedSettings['match_auto_accept_threshold'] ?? self::SIMILARITY_THRESHOLD_TO_ACCEPT_MATCH);

            // Update status to processing
            $document->status = 'processing';
            $document->save();

            // Step 1: Extract text from all files
            $extractedText = $this->extractTextFromFiles($document, $config, $resolvedSettings);

            if (empty($extractedText)) {
                throw new Exception('No text could be extracted from document files');
            }

            // Step 2: Extract core transaction data
            $rawData = $this->extractTransactionData(
                $config,
                $document,
                $extractedText,
                $document->custom_prompt,
                data_get($resolvedSettings, 'generic_document_language'),
            );

            Log::debug('AI extracted raw transaction data', ['raw_data' => $rawData]);

            // Step 3: Determine transaction type
            $transactionType = Str::lower($rawData['transaction_type'] ?? 'withdrawal');

            // Step 4: Match assets based on transaction type
            $accountId = null;
            $accountFromId = null;
            $accountToId = null;
            $investmentId = null;
            $matchedPayeeId = null;

            if (in_array($transactionType, TransactionTypeEnum::investmentTypeValues())) {
                // Investment transaction: match account and investment
                if (!empty($rawData['account'])) {
                    $accountId = $this->matchAccount($config, $document, $user, $rawData['account'], $autoAcceptThreshold);
                }
                if (!empty($rawData['investment'])) {
                    $investmentId = $this->matchInvestment($config, $document, $user, $rawData['investment'], $autoAcceptThreshold);
                }
            } elseif ($transactionType === 'transfer') {
                // Transfer: match two accounts
                if (!empty($rawData['account_from'])) {
                    $accountFromId = $this->matchAccount($config, $document, $user, $rawData['account_from'], $autoAcceptThreshold);
                }
                if (!empty($rawData['account_to'])) {
                    $accountToId = $this->matchAccount($config, $document, $user, $rawData['account_to'], $autoAcceptThreshold);
                }
            } elseif ($transactionType === 'withdrawal') {
                // Withdrawal: match account (from) and payee (to)
                if (!empty($rawData['account'])) {
                    $accountFromId = $this->matchAccount($config, $document, $user, $rawData['account'], $autoAcceptThreshold);
                }
                if (!empty($rawData['payee'])) {
                    $accountToId = $this->matchPayee($config, $document, $user, $rawData['payee'], $autoAcceptThreshold);
                    $matchedPayeeId = $accountToId;
                }
            } elseif ($transactionType === 'deposit') {
                // Deposit: match payee (from) and account (to)
                if (!empty($rawData['payee'])) {
                    $accountFromId = $this->matchPayee($config, $document, $user, $rawData['payee'], $autoAcceptThreshold);
                    $matchedPayeeId = $accountFromId;
                }
                if (!empty($rawData['account'])) {
                    $accountToId = $this->matchAccount($config, $document, $user, $rawData['account'], $autoAcceptThreshold);
                }
            }

            $payeeCategoryShortcutItem = $this->resolvePayeeCategoryShortcutItem(
                $user,
                $transactionType,
                $matchedPayeeId,
                $rawData,
                $document,
            );

            // Step 4: Build final transaction data structure
            $transactionData = $this->buildTransactionData(
                $rawData,
                $transactionType,
                $accountId,
                $accountFromId,
                $accountToId,
                $investmentId,
                $payeeCategoryShortcutItem,
                $user,
                $config,
                $document,
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
    /**
     * @param  array<string, mixed>  $aiUserSettings
     */
    private function extractTextFromFiles(AiDocument $document, AiProviderConfig $config, array $aiUserSettings): string
    {
        $texts = [];

        foreach ($document->aiDocumentFiles as $file) {
            try {
                $text = $this->textExtractor->extractFromFile(
                    filePath: $file->file_path,
                    fileType: $file->file_type,
                    visionConfig: $config,
                    aiUserSettings: $aiUserSettings,
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
    protected function extractTransactionData(
        AiProviderConfig $config,
        AiDocument $document,
        string $text,
        ?string $customPrompt = null,
        ?string $genericDocumentLanguage = null,
    ): array {
        $prompt = $this->aiPromptBuilder->buildMainExtractionPrompt($text, $customPrompt, $genericDocumentLanguage);
        try {
            $data = $this->requestMainExtractionPayload($config, $document, $prompt);
            $data = $this->normalizeMainExtractionPayload($data);

            $this->aiExtractionSchemaValidator->validate($data);

            Log::debug('Parsed main AI response', [
                'prompt' => $prompt,
                'data' => $data
            ]);

            return $data;
        } catch (AiResponseParseException $e) {
            Log::error('Failed to parse main AI response', [
                'response' => null,
                'error' => $e->getMessage(),
            ]);

            throw InvalidAiResponseSchemaException::invalidPayloadStructure($e->getMessage(), $e);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizeMainExtractionPayload(array $data): array
    {
        $transactionType = Str::lower((string) ($data['transaction_type'] ?? 'withdrawal'));

        $standardKeys = [
            'transaction_type',
            'account',
            'account_from',
            'account_to',
            'payee',
            'date',
            'amount',
            'currency',
            'transaction_items',
        ];

        $investmentKeys = [
            'transaction_type',
            'account',
            'investment',
            'date',
            'amount',
            'quantity',
            'price',
            'commission',
            'tax',
            'dividend',
            'currency',
        ];

        $keys = in_array($transactionType, TransactionTypeEnum::investmentTypeValues(), true)
            ? $investmentKeys
            : $standardKeys;

        $normalized = [];

        foreach ($keys as $key) {
            if (! array_key_exists($key, $data)) {
                continue;
            }

            $normalized[$key] = $data[$key];
        }

        return $normalized;
    }

    /**
     * Match account name to user's accounts
     */
    private function matchAccount(AiProviderConfig $config, AiDocument $document, User $user, string $accountName, float $autoAcceptThreshold): ?int
    {
        // Create service instance with user context
        $matchingService = new AssetMatchingService($user);

        // Get similar accounts - this already limits to top N matches
        $similarAccounts = $matchingService->matchAccounts($accountName);

        if (empty($similarAccounts)) {
            Log::debug('No similar accounts found', ['account_name' => $accountName]);

            $this->appendLocalProcessingHistory(
                $document,
                'account_matching',
                [
                    'account_name' => $accountName,
                    'reason' => 'No similar accounts found for matching',
                ],
                [
                    'matched_id' => null,
                ]
            );

            return null;
        }

        // If the top match has high confidence, return it immediately to save AI calls
        $topMatch = reset($similarAccounts);
        if ($topMatch['similarity'] >= $autoAcceptThreshold) {
            Log::info('High confidence match found for account name, skipping AI call', [
                'account_name' => $accountName,
                'account_name_in_list' => $topMatch['name'],
                'matched_id' => $topMatch['id'],
                'similarity' => $topMatch['similarity'],
            ]);

            $this->appendProcessingHistory(
                $document,
                'account_matching',
                "Local account matching used. AI call skipped because similarity reached threshold " . $autoAcceptThreshold . ". Input: \"{$accountName}\".",
                "Matched account ID {$topMatch['id']} ({$topMatch['name']}) with similarity {$topMatch['similarity']}."
            );

            return $topMatch['id'];
        }

        // Format for AI prompt
        $accountsList = collect($similarAccounts)
            ->map(fn ($match) => "{$match['id']}: {$match['name']}")
            ->join("\n");

        $prompt = $this->aiPromptBuilder->buildAccountMatchingPrompt($accountsList, $accountName);

        $response = $this->requestAccountMatchResult($config, $document, $prompt);

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
    private function matchPayee(AiProviderConfig $config, AiDocument $document, User $user, string $payeeName, float $autoAcceptThreshold): ?int
    {
        // Create service instance with user context
        $matchingService = new AssetMatchingService($user);

        // Get similar payees - service limits to top N matches
        $similarPayees = $matchingService->matchPayees($payeeName);

        if (empty($similarPayees)) {
            Log::debug('No similar payees found', ['payee_name' => $payeeName]);

            $this->appendLocalProcessingHistory(
                $document,
                'payee_matching',
                [
                    'payee_name' => $payeeName,
                    'reason' => 'No similar payees found for matching',
                ],
                [
                    'matched_id' => null,
                ]
            );

            return null;
        }

        // If the top match has high confidence, return it immediately to save AI calls
        $topMatch = reset($similarPayees);
        if ($topMatch['similarity'] >= $autoAcceptThreshold) {
            Log::info('High confidence match found for payee name, skipping AI call', [
                'payee_name' => $payeeName,
                'payee_name_in_list' => $topMatch['name'],
                'matched_id' => $topMatch['id'],
                'similarity' => $topMatch['similarity'],
            ]);

            $this->appendProcessingHistory(
                $document,
                'payee_matching',
                "Local payee matching used. AI call skipped because similarity reached threshold " . $autoAcceptThreshold . ". Input: \"{$payeeName}\".",
                "Matched payee ID {$topMatch['id']} ({$topMatch['name']}) with similarity {$topMatch['similarity']}."
            );

            return $topMatch['id'];
        }

        // Format for AI prompt
        $payeesList = collect($similarPayees)
            ->map(fn ($match) => "{$match['id']}: {$match['name']}")
            ->join("\n");

        $prompt = $this->aiPromptBuilder->buildPayeeMatchingPrompt($payeesList, $payeeName);

        $response = $this->requestPayeeMatchResult($config, $document, $prompt);

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
    private function matchInvestment(AiProviderConfig $config, AiDocument $document, User $user, string $investmentName, float $autoAcceptThreshold): ?int
    {
        // Create service instance with user context
        $matchingService = new AssetMatchingService($user);

        // Get similar investments - this already limits to top N matches
        $similarInvestments = $matchingService->matchInvestments($investmentName);

        if (empty($similarInvestments)) {
            Log::debug('No similar investments found', ['investment_name' => $investmentName]);

            $this->appendLocalProcessingHistory(
                $document,
                'investment_matching',
                [
                    'investment_name' => $investmentName,
                    'reason' => 'No similar investments found for matching',
                ],
                [
                    'matched_id' => null,
                ]
            );

            return null;
        }

        // If the top match has high confidence, return it immediately to save AI calls
        $topMatch = reset($similarInvestments);
        if ($topMatch['similarity'] >= $autoAcceptThreshold) {
            Log::info('High confidence match found for investment name, skipping AI call', [
                'investment_name' => $investmentName,
                'investment_name_in_list' => $topMatch['name'],
                'matched_id' => $topMatch['id'],
                'similarity' => $topMatch['similarity'],
            ]);

            $this->appendLocalProcessingHistory(
                $document,
                'investment_matching',
                [
                    'investment_name' => $investmentName,
                    'path' => 'high_confidence_match',
                    'threshold' => $autoAcceptThreshold,
                ],
                [
                    'matched_id' => $topMatch['id'],
                    'matched_name' => $topMatch['name'],
                    'similarity' => $topMatch['similarity'],
                ]
            );

            return $topMatch['id'];
        }

        // Format for AI prompt
        $investmentsList = collect($similarInvestments)
            ->map(fn ($match) => "{$match['id']}: {$match['name']}")
            ->join("\n");

        $prompt = $this->aiPromptBuilder->buildInvestmentMatchingPrompt($investmentsList, $investmentName);

        $response = $this->requestInvestmentMatchResult($config, $document, $prompt);

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
        ?array $payeeCategoryShortcutItem,
        User $user,
        AiProviderConfig $config,
        AiDocument $document,
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

            if ($payeeCategoryShortcutItem !== null) {
                $data['transaction_items'] = [$payeeCategoryShortcutItem];

                return $data;
            }

        }

        // Build transaction_items array with category learning (batch AI matching)
        if (! $isInvestment && isset($rawData['transaction_items']) && is_array($rawData['transaction_items'])) {
            $data['transaction_items'] = $this->matchCategoriesForItems($rawData['transaction_items'], $user, $config, $document);
        }

        return $data;
    }

    /**
     * Resolve a single category item based on payee category stats.
     *
     * If exactly one category is used by this payee in the last 6 months,
     * category matching can be skipped and the whole amount is assigned to it.
     *
     * @return array{amount: float, description: string, recommended_category_id: int, match_type: string, confidence_score: float}|null
     */
    protected function resolvePayeeCategoryShortcutItem(
        User $user,
        string $transactionType,
        ?int $payeeId,
        array $rawData,
        ?AiDocument $document = null,
    ): ?array {
        if (! in_array($transactionType, ['withdrawal', 'deposit'], true) || $payeeId === null) {
            return null;
        }

        /** @var AccountEntity|null $payee */
        $payee = $user->payees()->active()->find($payeeId);

        if (! $payee) {
            return null;
        }

        $categoryStats = $this->payeeCategoryStatsService->getCategoryStatsForPayee($user, $payee, 6);

        if ($categoryStats->count() !== 1) {
            return null;
        }

        $categoryId = (int) $categoryStats->first()['category_id'];
        $amount = floatval($rawData['amount'] ?? 0);
        $description = $this->normalizeTransactionItemDescription(
            $rawData['transaction_items'][0]['description'] ?? ($rawData['payee'] ?? '')
        );

        Log::info('Single-category payee shortcut applied', [
            'user_id' => $user->id,
            'payee_id' => $payee->id,
            'category_id' => $categoryId,
            'transaction_type' => $transactionType,
        ]);

        if ($document !== null) {
            $this->appendLocalProcessingHistory(
                $document,
                'category_batch_matching',
                [
                    'path' => 'single_payee_category_shortcut',
                    'payee_id' => $payee->id,
                    'payee_name' => $payee->name,
                    'transaction_type' => $transactionType,
                    'description' => (string) $description,
                ],
                [
                    'recommended_category_id' => $categoryId,
                    'match_type' => 'exact',
                    'confidence_score' => 1.0,
                ]
            );
        }

        return [
            'amount' => $amount,
            'description' => (string) $description,
            'recommended_category_id' => $categoryId,
            'match_type' => 'exact',
            'confidence_score' => 1.0,
        ];
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

        $normalized = $this->categoryLearningService->normalize($description);

        // Try to find exact match in learning data with active category
        $learning = $user->categoryLearning()
            ->where('item_description', $normalized)
            ->whereHas('category', fn ($q) => $q->where('active', 1))
            ->orderByDesc('usage_count')
            ->first();

        /** @var CategoryLearning|null $learning */

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
    protected function matchCategoriesForItems(array $items, User $user, AiProviderConfig $config, AiDocument $document): array
    {
        $enrichedItems = [];
        $itemsNeedingAi = [];
        $aiItemIndexMap = [];

        // First pass: Check for exact matches
        foreach ($items as $index => $item) {
            $description = $this->normalizeTransactionItemDescription($item['description'] ?? '');
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

                $this->appendLocalProcessingHistory(
                    $document,
                    'category_batch_matching',
                    [
                        'path' => 'Exact learning found and used',
                        'description' => $description,
                    ],
                    [
                        'recommended_category_id' => $exactMatch['recommended_category_id'],
                    ]
                );
            } else {
                // No exact match, will need AI
                $aiItemIndex = count($itemsNeedingAi);
                $itemsNeedingAi[$aiItemIndex] = [
                    'description' => $description,
                ];
                $aiItemIndexMap[$aiItemIndex] = $index;
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
            $aiMatches = $this->matchCategoriesBatch($itemsNeedingAi, $user, $config, $document);

            // Merge AI results back into enriched items
            // All recommendations (exact or AI) populate recommended_category_id
            foreach ($aiMatches as $aiItemIndex => $aiMatch) {
                $originalIndex = $aiItemIndexMap[$aiItemIndex] ?? null;

                if ($originalIndex !== null && isset($enrichedItems[$originalIndex])) {
                    $enrichedItems[$originalIndex]['recommended_category_id'] = $aiMatch['recommended_category_id'];
                    $enrichedItems[$originalIndex]['match_type'] = $aiMatch['match_type'];
                    $enrichedItems[$originalIndex]['confidence_score'] = $aiMatch['confidence_score'];
                }
            }
        } catch (Exception $e) {
            if (! $this->hasAiFailureFallbackHistory($document, 'category_batch_matching')) {
                $this->appendAiFallbackHistoryAfterFailure(
                    $document,
                    'category_batch_matching',
                    'Category batch matching call failed before a full AI response was produced.',
                    $e,
                );
            }

            Log::error('Category batch matching failed; document processing will fail', [
                'error' => $e->getMessage(),
                'item_count' => count($itemsNeedingAi),
            ]);

            throw $e;
        }

        return array_values($enrichedItems);
    }

    /**
     * Match categories using AI for multiple items in a single batch call
     *
     * @param  array  $items  Items needing AI matching (indexed by original position)
     * @return array AI match results indexed by original position
     */
    protected function matchCategoriesBatch(array $items, User $user, AiProviderConfig $config, AiDocument $document): array
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

        $resolvedSettings = $this->resolveAiUserSettings($user);
        $categoryPromptContext = $matchingService->resolveCategoryPromptContext(
            $user,
            (string) ($resolvedSettings['category_matching_mode'] ?? AiUserSettingsResolver::DEFAULT_CATEGORY_MATCHING_MODE)
        );

        // Build AI prompt with all items
        $prompt = $this->aiPromptBuilder->buildCategoryMatchingPrompt(
            $items,
            $learningContext,
            $categoryPromptContext['categories_list'],
            $categoryPromptContext['applied_category_matching_mode'],
            $categoryPromptContext['categories'],
            data_get($resolvedSettings, 'generic_document_language'),
        );

        $aiResults = $this->requestCategoryBatchMatches($config, $document, $prompt);

        Log::debug('Parsed category matching AI response', ['results' => $aiResults]);

        // Validate and format results
        $matches = [];
        foreach ($aiResults as $result) {
            $itemIndex = $result['item_index'] ?? null;
            $categoryId = $result['recommended_category_id'] ?? null;
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
    }

    private function normalizeTransactionItemDescription(?string $description): string
    {
        return Str::lower(Str::trim((string) $description));
    }

    protected function requestMainExtractionPayload(AiProviderConfig $config, AiDocument $document, string $prompt): array
    {
        return $this->aiStepGateway->extractMainData($config, $document, $prompt, $this->promptChatHistoryEnabled);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function requestCategoryBatchMatches(AiProviderConfig $config, AiDocument $document, string $prompt): array
    {
        return $this->aiStepGateway->matchCategoriesBatch($config, $document, $prompt, $this->promptChatHistoryEnabled);
    }

    protected function requestAccountMatchResult(AiProviderConfig $config, AiDocument $document, string $prompt): string
    {
        return $this->aiStepGateway->matchAccountId($config, $document, $prompt, $this->promptChatHistoryEnabled);
    }

    protected function requestPayeeMatchResult(AiProviderConfig $config, AiDocument $document, string $prompt): string
    {
        return $this->aiStepGateway->matchPayeeId($config, $document, $prompt, $this->promptChatHistoryEnabled);
    }

    protected function requestInvestmentMatchResult(AiProviderConfig $config, AiDocument $document, string $prompt): string
    {
        return $this->aiStepGateway->matchInvestmentId($config, $document, $prompt, $this->promptChatHistoryEnabled);
    }

    private function appendAiFallbackHistoryAfterFailure(AiDocument $document, string $step, string $prompt, Exception $exception): void
    {
        $this->processingHistoryRecorder->appendAiFallbackHistoryAfterFailure($document, $step, $prompt, $exception);
    }

    private function hasAiFailureFallbackHistory(AiDocument $document, string $step): bool
    {
        return $this->processingHistoryRecorder->hasAiFailureFallbackHistory($document, $step);
    }

    private function appendLocalProcessingHistory(AiDocument $document, string $step, array $context, array $result): void
    {
        $this->processingHistoryRecorder->appendLocalProcessingHistory($document, $step, $context, $result);
    }

    private function appendProcessingHistory(
        AiDocument $document,
        string $step,
        string $prompt,
        string $response,
        bool $includeInPromptHistory = true,
    ): void {
        $this->processingHistoryRecorder->appendProcessingHistory(
            $document,
            $step,
            $prompt,
            $response,
            $includeInPromptHistory,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveAiUserSettings(User $user): array
    {
        return $this->aiUserSettingsResolver->resolveForUser($user);
    }
}
