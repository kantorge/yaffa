<?php

namespace Tests\Unit\Services;

use App\Enums\TransactionType as TransactionTypeEnum;
use App\Exceptions\InvalidAiResponseSchemaException;
use App\Models\Account;
use App\Models\AccountEntity;
use App\Models\AiDocument;
use App\Models\AiProviderConfig;
use App\Models\Category;
use App\Models\Payee;
use App\Models\Transaction;
use App\Models\TransactionDetailStandard;
use App\Models\TransactionItem;
use App\Models\User;
use App\Services\AssetMatchingService;
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
            $this->createMock(AssetMatchingService::class),
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
            $this->createMock(AssetMatchingService::class),
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

    public function test_ai_category_matches_are_stored_as_recommendations(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create([
            'active' => 1,
        ]);
        $config = AiProviderConfig::factory()->for($user)->create();

        $service = new class (
            $this->createMock(TextExtractionService::class),
            $this->createMock(AssetMatchingService::class),
            $this->createMock(CategoryLearningService::class),
            $this->createMock(PayeeCategoryStatsService::class),
            new AiExtractionSchemaValidator(),
            new AiPromptBuilder(),
            $category->id
        ) extends ProcessDocumentService {
            public function __construct(
                TextExtractionService $textExtractor,
                AssetMatchingService $assetMatchingService,
                CategoryLearningService $categoryLearningService,
                PayeeCategoryStatsService $payeeCategoryStatsService,
                AiExtractionSchemaValidator $aiExtractionSchemaValidator,
                AiPromptBuilder $aiPromptBuilder,
                private int $categoryId
            ) {
                parent::__construct(
                    $textExtractor,
                    $assetMatchingService,
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
            $this->createMock(AssetMatchingService::class),
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

        $promptPayload = json_decode($result['history'][0]['prompt'], true, 512, JSON_THROW_ON_ERROR);
        $responsePayload = json_decode($result['history'][0]['response'], true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('local', $promptPayload['source']);
        $this->assertSame('exact_learning_match', $promptPayload['context']['path']);
        $this->assertSame(0, $promptPayload['context']['item_index']);
        $this->assertSame('local', $responsePayload['source']);
        $this->assertSame($category->id, $responsePayload['result']['recommended_category_id']);
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
            $this->createMock(AssetMatchingService::class),
            $categoryLearningService,
            $this->createMock(PayeeCategoryStatsService::class),
            new AiExtractionSchemaValidator(),
            new AiPromptBuilder(),
            $aiCategory->id
        ) extends ProcessDocumentService {
            public function __construct(
                TextExtractionService $textExtractor,
                AssetMatchingService $assetMatchingService,
                CategoryLearningService $categoryLearningService,
                PayeeCategoryStatsService $payeeCategoryStatsService,
                AiExtractionSchemaValidator $aiExtractionSchemaValidator,
                AiPromptBuilder $aiPromptBuilder,
                private int $aiCategoryId,
            ) {
                parent::__construct(
                    $textExtractor,
                    $assetMatchingService,
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
            $this->createMock(AssetMatchingService::class),
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

        $shortcutPromptPayload = json_decode($document->ai_chat_history[0]['prompt'], true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('single_payee_category_shortcut', $shortcutPromptPayload['context']['path']);
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
