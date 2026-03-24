<?php

namespace Tests\Unit\Services;

use App\Enums\TransactionType as TransactionTypeEnum;
use App\Exceptions\InvalidAiResponseSchemaException;
use App\Models\Account;
use App\Models\AccountEntity;
use App\Models\AiDocument;
use App\Models\AiDocumentFile;
use App\Models\AiProviderConfig;
use App\Models\AiUserSettings;
use App\Models\Category;
use App\Models\Payee;
use App\Models\Transaction;
use App\Models\TransactionDetailStandard;
use App\Models\TransactionItem;
use App\Models\User;
use App\Services\AiExtractionSchemaValidator;
use App\Services\AiPromptBuilder;
use App\Services\CategoryLearningService;
use App\Services\PayeeCategoryStatsService;
use App\Services\ProcessDocumentService;
use App\Services\TextExtractionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use RuntimeException;

class ProcessDocumentServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_extract_transaction_data_throws_typed_exception_for_malformed_ai_json(): void
    {
        $user = User::factory()->create();
        $config = AiProviderConfig::factory()->for($user)->create();

        $service = new class (
            $this->createMock(TextExtractionService::class),
            $this->createMock(CategoryLearningService::class),
            $this->createMock(PayeeCategoryStatsService::class),
            new AiExtractionSchemaValidator(),
            new AiPromptBuilder()
        ) extends ProcessDocumentService {
            public function extractData(AiProviderConfig $config, string $text): array
            {
                $document = AiDocument::factory()->for($config->user)->create();

                return $this->extractTransactionData($config, $document, $text);
            }

            protected function callAi(AiProviderConfig $config, AiDocument $document, string $prompt, string $step): string
            {
                return '{"transaction_type":"withdrawal"';
            }
        };

        $this->expectException(InvalidAiResponseSchemaException::class);
        $this->expectExceptionMessage('AI response is not valid JSON');

        $service->extractData($config, 'Receipt text');
    }

    public function test_extract_transaction_data_throws_typed_exception_for_invalid_schema(): void
    {
        $user = User::factory()->create();
        $config = AiProviderConfig::factory()->for($user)->create();

        $service = new class (
            $this->createMock(TextExtractionService::class),
            $this->createMock(CategoryLearningService::class),
            $this->createMock(PayeeCategoryStatsService::class),
            new AiExtractionSchemaValidator(),
            new AiPromptBuilder()
        ) extends ProcessDocumentService {
            public function extractData(AiProviderConfig $config, string $text): array
            {
                $document = AiDocument::factory()->for($config->user)->create();

                return $this->extractTransactionData($config, $document, $text);
            }

            protected function callAi(AiProviderConfig $config, AiDocument $document, string $prompt, string $step): string
            {
                return json_encode([
                    'transaction_type' => 'withdrawal',
                    'account' => 'Main account',
                    'account_from' => null,
                    'account_to' => null,
                    'payee' => 'Coffee Shop',
                    'date' => '2026-02-25',
                    'amount' => 4.5,
                    'currency' => 'USD',
                ]) ?: '';
            }
        };

        $this->expectException(InvalidAiResponseSchemaException::class);
        $this->expectExceptionMessage('missing required keys');

        $service->extractData($config, 'Receipt text');
    }

    public function test_extract_transaction_data_uses_structured_main_extraction_payload(): void
    {
        $user = User::factory()->create();
        $config = AiProviderConfig::factory()->for($user)->create();

        $service = new class (
            $this->createMock(TextExtractionService::class),
            $this->createMock(CategoryLearningService::class),
            $this->createMock(PayeeCategoryStatsService::class),
            new AiExtractionSchemaValidator(),
            new AiPromptBuilder()
        ) extends ProcessDocumentService {
            public function extractData(AiProviderConfig $config, string $text): array
            {
                $document = AiDocument::factory()->for($config->user)->create();

                return $this->extractTransactionData($config, $document, $text);
            }

            protected function callStructuredAiForMainExtraction(AiProviderConfig $config, AiDocument $document, string $prompt): array
            {
                return [
                    'structured' => [
                        'transaction_type' => 'withdrawal',
                        'account' => 'Main account',
                        'account_from' => null,
                        'account_to' => null,
                        'payee' => 'Coffee shop',
                        'date' => '2026-03-01',
                        'amount' => 12.5,
                        'currency' => 'USD',
                        'transaction_items' => [
                            ['description' => 'coffee', 'amount' => 12.5],
                        ],
                        'investment' => null,
                        'quantity' => null,
                        'price' => null,
                        'commission' => null,
                        'tax' => null,
                        'dividend' => null,
                    ],
                    'text' => "```json\n{\"ignored\":true}\n```",
                ];
            }
        };

        $result = $service->extractData($config, 'Receipt text');

        $this->assertSame('withdrawal', $result['transaction_type']);
        $this->assertSame('Main account', $result['account']);
        $this->assertSame(12.5, $result['amount']);
        $this->assertSame('coffee', $result['transaction_items'][0]['description']);
    }

    public function test_ai_category_matches_are_stored_as_recommendations(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create([
            'active' => 1,
        ]);
        $config = AiProviderConfig::factory()->for($user)->create();

        $service = new class (
            $this->createMock(TextExtractionService::class),
            $this->createMock(CategoryLearningService::class),
            $this->createMock(PayeeCategoryStatsService::class),
            new AiExtractionSchemaValidator(),
            new AiPromptBuilder(),
            $category->id
        ) extends ProcessDocumentService {
            public function __construct(
                TextExtractionService $textExtractor,
                CategoryLearningService $categoryLearningService,
                PayeeCategoryStatsService $payeeCategoryStatsService,
                AiExtractionSchemaValidator $aiExtractionSchemaValidator,
                AiPromptBuilder $aiPromptBuilder,
                private int $categoryId
            ) {
                parent::__construct(
                    $textExtractor,
                    $categoryLearningService,
                    $payeeCategoryStatsService,
                    $aiExtractionSchemaValidator,
                    $aiPromptBuilder
                );
            }

            public function matchCategories(array $items, User $user, AiProviderConfig $config): array
            {
                $document = AiDocument::factory()->for($user)->create();

                return $this->matchCategoriesForItems($items, $user, $config, $document);
            }

            protected function callAi(AiProviderConfig $config, AiDocument $document, string $prompt, string $step): string
            {
                return json_encode([
                    [
                        'item_index' => 0,
                        'recommended_category_id' => $this->categoryId,
                        'confidence_score' => 0.4,
                    ],
                ]);
            }
        };

        $result = $service->matchCategories([
            ['description' => 'Coffee beans', 'amount' => 12.5],
        ], $user, $config);

        $this->assertCount(1, $result);
        $this->assertSame(12.5, $result[0]['amount']);
        $this->assertSame('coffee beans', $result[0]['description']);
        $this->assertSame('ai', $result[0]['match_type']);
        $this->assertSame(0.4, $result[0]['confidence_score']);
        $this->assertSame($category->id, $result[0]['recommended_category_id']);
    }

    public function test_exact_category_match_is_logged_to_ai_chat_history_without_ai_call(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create([
            'active' => 1,
        ]);
        $config = AiProviderConfig::factory()->for($user)->create();

        $categoryLearningService = new CategoryLearningService();
        $user->categoryLearning()->create([
            'item_description' => $categoryLearningService->normalize('Coffee beans'),
            'category_id' => $category->id,
            'usage_count' => 3,
        ]);

        $service = new class (
            $this->createMock(TextExtractionService::class),
            $categoryLearningService,
            $this->createMock(PayeeCategoryStatsService::class),
            new AiExtractionSchemaValidator(),
            new AiPromptBuilder()
        ) extends ProcessDocumentService {
            public function matchCategoriesWithHistory(array $items, User $user, AiProviderConfig $config): array
            {
                $document = AiDocument::factory()->for($user)->create();

                $matchedItems = $this->matchCategoriesForItems($items, $user, $config, $document);

                $document->refresh();

                return [
                    'items' => $matchedItems,
                    'history' => $document->ai_chat_history,
                ];
            }

            protected function callAi(AiProviderConfig $config, AiDocument $document, string $prompt, string $step): string
            {
                throw new RuntimeException('AI should not be called for exact category matches');
            }
        };

        $result = $service->matchCategoriesWithHistory([
            ['description' => 'Coffee beans', 'amount' => 12.5],
        ], $user, $config);

        $this->assertCount(1, $result['items']);
        $this->assertSame($category->id, $result['items'][0]['recommended_category_id']);
        $this->assertSame('exact', $result['items'][0]['match_type']);
        $this->assertIsArray($result['history']);
        $this->assertCount(1, $result['history']);
        $this->assertSame('category_batch_matching', $result['history'][0]['step']);
        $this->assertFalse($result['history'][0]['include_in_prompt_history']);

        $historyPrompt = $result['history'][0]['prompt'];
        $historyResponse = $result['history'][0]['response'];

        $this->assertStringContainsString('Local Category Batch Matching decision (AI call skipped).', $historyPrompt);
        $this->assertStringContainsString('Context:', $historyPrompt);
        $this->assertStringContainsString('- Path: Exact learning found and used', $historyPrompt);
        $this->assertStringContainsString('- Description: coffee beans', $historyPrompt);
        $this->assertStringContainsString('Result:', $historyResponse);
        $this->assertStringContainsString('- Recommended Category Id: ' . $category->id, $historyResponse);
        $this->assertDoesNotMatchRegularExpression('/^\s*\{/', $historyPrompt);
        $this->assertDoesNotMatchRegularExpression('/^\s*\{/', $historyResponse);
    }

    public function test_mixed_exact_and_ai_category_matching_uses_ai_for_remaining_items_and_normalizes_descriptions(): void
    {
        $user = User::factory()->create();
        $exactCategory = Category::factory()->for($user)->create(['active' => 1]);
        $aiCategory = Category::factory()->for($user)->create(['active' => 1]);
        $config = AiProviderConfig::factory()->for($user)->create();

        $categoryLearningService = new CategoryLearningService();
        $user->categoryLearning()->create([
            'item_description' => $categoryLearningService->normalize('Coffee'),
            'category_id' => $exactCategory->id,
            'usage_count' => 5,
        ]);

        $service = new class (
            $this->createMock(TextExtractionService::class),
            $categoryLearningService,
            $this->createMock(PayeeCategoryStatsService::class),
            new AiExtractionSchemaValidator(),
            new AiPromptBuilder(),
            $aiCategory->id
        ) extends ProcessDocumentService {
            public function __construct(
                TextExtractionService $textExtractor,
                CategoryLearningService $categoryLearningService,
                PayeeCategoryStatsService $payeeCategoryStatsService,
                AiExtractionSchemaValidator $aiExtractionSchemaValidator,
                AiPromptBuilder $aiPromptBuilder,
                private int $aiCategoryId,
            ) {
                parent::__construct(
                    $textExtractor,
                    $categoryLearningService,
                    $payeeCategoryStatsService,
                    $aiExtractionSchemaValidator,
                    $aiPromptBuilder,
                );
            }

            public function matchCategories(array $items, User $user, AiProviderConfig $config): array
            {
                $document = AiDocument::factory()->for($user)->create();

                return $this->matchCategoriesForItems($items, $user, $config, $document);
            }

            protected function callAi(AiProviderConfig $config, AiDocument $document, string $prompt, string $step): string
            {
                return json_encode([
                    [
                        'item_index' => 0,
                        'recommended_category_id' => $this->aiCategoryId,
                        'confidence_score' => 0.77,
                    ],
                ]) ?: '[]';
            }
        };

        $result = $service->matchCategories([
            ['description' => 'COFFEE', 'amount' => 5],
            ['description' => 'BREAD ROLL', 'amount' => 2],
        ], $user, $config);

        $this->assertCount(2, $result);

        $this->assertSame('coffee', $result[0]['description']);
        $this->assertSame($exactCategory->id, $result[0]['recommended_category_id']);
        $this->assertSame('exact', $result[0]['match_type']);

        $this->assertSame('bread roll', $result[1]['description']);
        $this->assertSame($aiCategory->id, $result[1]['recommended_category_id']);
        $this->assertSame('ai', $result[1]['match_type']);
        $this->assertSame(0.77, $result[1]['confidence_score']);
    }

    public function test_high_confidence_account_and_payee_matches_are_logged_with_readable_history(): void
    {
        $user = User::factory()->create();
        AiUserSettings::factory()->create([
            'user_id' => $user->id,
            'match_auto_accept_threshold' => 0.88,
        ]);

        $config = AiProviderConfig::factory()->for($user)->create();

        $accountName = 'Main Wallet';
        $payeeName = 'Coffee Shop';

        $account = AccountEntity::factory()
            ->for($user)
            ->for(Account::factory()->withUser($user), 'config')
            ->create([
                'config_type' => 'account',
                'active' => true,
                'name' => $accountName,
                'alias' => null,
            ]);

        $payee = AccountEntity::factory()
            ->for($user)
            ->for(Payee::factory()->withUser($user), 'config')
            ->create([
                'config_type' => 'payee',
                'active' => true,
                'name' => $payeeName,
                'alias' => null,
            ]);

        $document = AiDocument::factory()
            ->for($user)
            ->create([
                'status' => 'ready_for_processing',
                'source_type' => 'manual_upload',
            ]);

        AiDocumentFile::factory()->create([
            'ai_document_id' => $document->id,
            'file_path' => 'ai_documents/test/input.txt',
            'file_name' => 'input.txt',
            'file_type' => 'txt',
        ]);

        $textExtractor = $this->createMock(TextExtractionService::class);
        $textExtractor->expects($this->once())
            ->method('extractFromFile')
            ->willReturn('Receipt text');

        $service = new class (
            $textExtractor,
            new CategoryLearningService(),
            new PayeeCategoryStatsService(),
            new AiExtractionSchemaValidator(),
            new AiPromptBuilder(),
            $accountName,
            $payeeName,
        ) extends ProcessDocumentService {
            public function __construct(
                TextExtractionService $textExtractor,
                CategoryLearningService $categoryLearningService,
                PayeeCategoryStatsService $payeeCategoryStatsService,
                AiExtractionSchemaValidator $aiExtractionSchemaValidator,
                AiPromptBuilder $aiPromptBuilder,
                private string $accountName,
                private string $payeeName,
            ) {
                parent::__construct(
                    $textExtractor,
                    $categoryLearningService,
                    $payeeCategoryStatsService,
                    $aiExtractionSchemaValidator,
                    $aiPromptBuilder,
                );
            }

            protected function callAi(AiProviderConfig $config, AiDocument $document, string $prompt, string $step): string
            {
                if ($step !== 'main_extraction') {
                    throw new RuntimeException("Unexpected AI call for step {$step}");
                }

                return json_encode([
                    'transaction_type' => 'withdrawal',
                    'account' => $this->accountName,
                    'account_from' => null,
                    'account_to' => null,
                    'payee' => $this->payeeName,
                    'date' => '2026-02-25',
                    'amount' => 9.99,
                    'currency' => 'USD',
                    'transaction_items' => [],
                ]) ?: '';
            }
        };

        $result = $service->process($document);

        $this->assertTrue($result['success']);

        $document->refresh();
        $this->assertSame('ready_for_review', $document->status);
        $this->assertIsArray($document->ai_chat_history);

        $historyByStep = collect($document->ai_chat_history)
            ->keyBy('step')
            ->all();

        $this->assertArrayHasKey('account_matching', $historyByStep);
        $this->assertArrayHasKey('payee_matching', $historyByStep);

        $accountHistory = $historyByStep['account_matching'];
        $payeeHistory = $historyByStep['payee_matching'];

        $this->assertStringContainsString('Local account matching used. AI call skipped because similarity reached threshold', $accountHistory['prompt']);
        $this->assertStringContainsString('threshold 0.88', $accountHistory['prompt']);
        $this->assertStringContainsString("Input: \"{$accountName}\".", $accountHistory['prompt']);
        $this->assertStringContainsString("Matched account ID {$account->id} ({$accountName})", $accountHistory['response']);
        $this->assertDoesNotMatchRegularExpression('/^\s*\{/', $accountHistory['prompt']);
        $this->assertDoesNotMatchRegularExpression('/^\s*\{/', $accountHistory['response']);

        $this->assertStringContainsString('Local payee matching used. AI call skipped because similarity reached threshold', $payeeHistory['prompt']);
        $this->assertStringContainsString('threshold 0.88', $payeeHistory['prompt']);
        $this->assertStringContainsString("Input: \"{$payeeName}\".", $payeeHistory['prompt']);
        $this->assertStringContainsString("Matched payee ID {$payee->id} ({$payeeName})", $payeeHistory['response']);
        $this->assertDoesNotMatchRegularExpression('/^\s*\{/', $payeeHistory['prompt']);
        $this->assertDoesNotMatchRegularExpression('/^\s*\{/', $payeeHistory['response']);
    }

    public function test_process_passes_user_ocr_and_image_settings_to_text_extractor(): void
    {
        $user = User::factory()->create();
        AiUserSettings::factory()->create([
            'user_id' => $user->id,
            'ocr_language' => 'hun',
            'image_max_width_vision' => 1666,
            'image_max_height_vision' => 1222,
            'image_quality_vision' => 71,
            'image_max_width_tesseract' => 1800,
            'image_max_height_tesseract' => 1400,
        ]);

        AiProviderConfig::factory()->for($user)->create();

        $document = AiDocument::factory()
            ->for($user)
            ->create([
                'status' => 'ready_for_processing',
                'source_type' => 'manual_upload',
            ]);

        AiDocumentFile::factory()->create([
            'ai_document_id' => $document->id,
            'file_path' => 'ai_documents/test/input.txt',
            'file_name' => 'input.txt',
            'file_type' => 'txt',
        ]);

        $textExtractor = $this->createMock(TextExtractionService::class);
        $textExtractor
            ->expects($this->once())
            ->method('extractFromFile')
            ->with(
                $this->equalTo('ai_documents/test/input.txt'),
                $this->equalTo('txt'),
                $this->isInstanceOf(AiProviderConfig::class),
                $this->callback(fn (array $settings): bool => $settings['ocr_language'] === 'hun'
                        && $settings['image_max_width_vision'] === 1666
                        && $settings['image_max_height_vision'] === 1222
                        && $settings['image_quality_vision'] === 71
                        && $settings['image_max_width_tesseract'] === 1800
                        && $settings['image_max_height_tesseract'] === 1400)
            )
            ->willReturn('Receipt text');

        $service = new class (
            $textExtractor,
            new CategoryLearningService(),
            $this->createMock(PayeeCategoryStatsService::class),
            new AiExtractionSchemaValidator(),
            new AiPromptBuilder(),
        ) extends ProcessDocumentService {
            protected function callAi(AiProviderConfig $config, AiDocument $document, string $prompt, string $step): string
            {
                return json_encode([
                    'transaction_type' => 'withdrawal',
                    'account' => null,
                    'account_from' => null,
                    'account_to' => null,
                    'payee' => null,
                    'date' => '2026-03-01',
                    'amount' => 12.5,
                    'currency' => 'HUF',
                    'transaction_items' => [],
                ]) ?: '';
            }
        };

        $result = $service->process($document);

        $this->assertTrue($result['success']);
        $document->refresh();
        $this->assertSame('ready_for_review', $document->status);
    }

    public function test_process_uses_generic_document_language_in_main_extraction_prompt(): void
    {
        $user = User::factory()->create();
        AiUserSettings::factory()->create([
            'user_id' => $user->id,
            'generic_document_language' => 'Hungarian',
        ]);

        AiProviderConfig::factory()->for($user)->create();

        $document = AiDocument::factory()
            ->for($user)
            ->create([
                'status' => 'ready_for_processing',
                'source_type' => 'manual_upload',
            ]);

        AiDocumentFile::factory()->create([
            'ai_document_id' => $document->id,
            'file_path' => 'ai_documents/test/input.txt',
            'file_name' => 'input.txt',
            'file_type' => 'txt',
        ]);

        $textExtractor = $this->createMock(TextExtractionService::class);
        $textExtractor
            ->expects($this->once())
            ->method('extractFromFile')
            ->willReturn('Receipt text');

        $service = new class (
            $textExtractor,
            $this->createMock(CategoryLearningService::class),
            $this->createMock(PayeeCategoryStatsService::class),
            new AiExtractionSchemaValidator(),
            new AiPromptBuilder(),
        ) extends ProcessDocumentService {
            public string $capturedPrompt = '';

            protected function callAi(AiProviderConfig $config, AiDocument $document, string $prompt, string $step): string
            {
                if ($step === 'main_extraction') {
                    $this->capturedPrompt = $prompt;
                }

                return json_encode([
                    'transaction_type' => 'withdrawal',
                    'account' => null,
                    'account_from' => null,
                    'account_to' => null,
                    'payee' => null,
                    'date' => '2026-03-01',
                    'amount' => 12.5,
                    'currency' => 'HUF',
                    'transaction_items' => [],
                ]) ?: '';
            }
        };

        $result = $service->process($document);

        $this->assertTrue($result['success']);
        $this->assertStringContainsString(
            'The document provided is expected to be in Hungarian.',
            $service->capturedPrompt,
        );
        $this->assertStringNotContainsString(
            'The language of the document may vary.',
            $service->capturedPrompt,
        );
    }

    public function test_single_payee_category_shortcut_assigns_whole_amount_to_one_item(): void
    {
        $user = User::factory()->create();

        $payee = AccountEntity::factory()
            ->for($user)
            ->for(Payee::factory()->withUser($user), 'config')
            ->create([
                'config_type' => 'payee',
                'active' => true,
                'name' => 'Coffee Shop',
            ]);

        $account = AccountEntity::factory()
            ->for($user)
            ->for(Account::factory()->withUser($user), 'config')
            ->create([
                'config_type' => 'account',
                'active' => true,
            ]);

        $category = Category::factory()->for($user)->create(['active' => 1]);

        $this->createTransactionWithCategory($user, $account->id, $payee->id, $category->id, now()->subMonths(1));
        $this->createTransactionWithCategory($user, $account->id, $payee->id, $category->id, now()->subMonths(2));

        $service = new class (
            $this->createMock(TextExtractionService::class),
            $this->createMock(CategoryLearningService::class),
            new PayeeCategoryStatsService(),
            new AiExtractionSchemaValidator(),
            new AiPromptBuilder()
        ) extends ProcessDocumentService {
            public function resolveShortcut(User $user, string $transactionType, ?int $payeeId, array $rawData, ?AiDocument $document = null): ?array
            {
                return $this->resolvePayeeCategoryShortcutItem($user, $transactionType, $payeeId, $rawData, $document);
            }

            protected function callAi(AiProviderConfig $config, AiDocument $document, string $prompt, string $step): string
            {
                return '[]';
            }
        };

        $document = AiDocument::factory()->for($user)->create();

        $result = $service->resolveShortcut($user, 'withdrawal', $payee->id, [
            'amount' => 42.5,
            'payee' => 'Coffee Shop',
        ], $document);

        $this->assertNotNull($result);
        $this->assertSame(42.5, $result['amount']);
        $this->assertSame('coffee shop', $result['description']);
        $this->assertSame($category->id, $result['recommended_category_id']);
        $this->assertSame('exact', $result['match_type']);
        $this->assertSame(1.0, $result['confidence_score']);

        $document->refresh();
        $this->assertIsArray($document->ai_chat_history);
        $this->assertCount(1, $document->ai_chat_history);
        $this->assertSame('category_batch_matching', $document->ai_chat_history[0]['step']);

        $shortcutPrompt = $document->ai_chat_history[0]['prompt'];
        $shortcutResponse = $document->ai_chat_history[0]['response'];

        $this->assertStringContainsString('Local Category Batch Matching decision (AI call skipped).', $shortcutPrompt);
        $this->assertStringContainsString('- Path: single_payee_category_shortcut', $shortcutPrompt);
        $this->assertStringContainsString('- Payee Name: Coffee Shop', $shortcutPrompt);
        $this->assertStringContainsString('- Recommended Category Id: ' . $category->id, $shortcutResponse);
        $this->assertDoesNotMatchRegularExpression('/^\s*\{/', $shortcutPrompt);
        $this->assertDoesNotMatchRegularExpression('/^\s*\{/', $shortcutResponse);
    }

    private function createTransactionWithCategory(
        User $user,
        int $accountId,
        int $payeeId,
        int $categoryId,
        Carbon $date
    ): void {
        $detail = TransactionDetailStandard::query()->create([
            'account_from_id' => $accountId,
            'account_to_id' => $payeeId,
            'amount_from' => 10,
            'amount_to' => 10,
        ]);

        $transaction = Transaction::query()->create([
            'user_id' => $user->id,
            'date' => $date,
            'transaction_type' => TransactionTypeEnum::WITHDRAWAL->value,
            'reconciled' => false,
            'schedule' => false,
            'budget' => false,
            'comment' => null,
            'config_type' => 'standard',
            'config_id' => $detail->id,
        ]);

        TransactionItem::query()->create([
            'transaction_id' => $transaction->id,
            'category_id' => $categoryId,
            'amount' => 10,
            'comment' => 'Test item',
        ]);
    }
}
