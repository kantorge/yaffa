<?php

namespace Tests\Unit\Services;

use App\Enums\TransactionType as TransactionTypeEnum;
use App\Exceptions\InvalidAiResponseSchemaException;
use App\Models\Account;
use App\Models\AccountEntity;
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
                return $this->extractTransactionData($config, $text);
            }

            protected function callAi(AiProviderConfig $config, string $prompt): string
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
                return $this->extractTransactionData($config, $text);
            }

            protected function callAi(AiProviderConfig $config, string $prompt): string
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
                return $this->matchCategoriesForItems($items, $user, $config);
            }

            protected function callAi(AiProviderConfig $config, string $prompt): string
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
        $this->assertSame('Coffee beans', $result[0]['description']);
        $this->assertSame('ai', $result[0]['match_type']);
        $this->assertSame(0.4, $result[0]['confidence_score']);
        $this->assertSame($category->id, $result[0]['recommended_category_id']);
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
            public function resolveShortcut(User $user, string $transactionType, ?int $payeeId, array $rawData): ?array
            {
                return $this->resolvePayeeCategoryShortcutItem($user, $transactionType, $payeeId, $rawData);
            }

            protected function callAi(AiProviderConfig $config, string $prompt): string
            {
                return '[]';
            }
        };

        $result = $service->resolveShortcut($user, 'withdrawal', $payee->id, [
            'amount' => 42.5,
            'payee' => 'Coffee Shop',
        ]);

        $this->assertNotNull($result);
        $this->assertSame(42.5, $result['amount']);
        $this->assertSame('Coffee Shop', $result['description']);
        $this->assertSame($category->id, $result['recommended_category_id']);
        $this->assertSame('exact', $result['match_type']);
        $this->assertSame(1.0, $result['confidence_score']);
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
