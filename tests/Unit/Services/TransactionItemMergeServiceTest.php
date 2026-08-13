<?php

namespace Tests\Unit\Services;

use App\Models\Category;
use App\Models\Tag;
use App\Models\Transaction;
use App\Models\User;
use App\Services\TransactionItemMergeService;
use Brick\Money\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

class TransactionItemMergeServiceTest extends TestCase
{
    use RefreshDatabase;

    private TransactionItemMergeService $service;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new TransactionItemMergeService();
        $this->user = User::factory()->create([
            'auto_merge_standard_transaction_items' => true,
        ]);
    }

    /**
     * Helper to create a standard (non-schedule, non-budget) withdrawal transaction.
     */
    private function createStandardTransaction(): Transaction
    {
        return Transaction::factory()
            ->withdrawal($this->user)
            ->create(['user_id' => $this->user->id, 'schedule' => false, 'budget' => false]);
    }

    /**
     * Helper to create a category for the test user.
     */
    private function createCategory(): Category
    {
        $parent = Category::factory()->for($this->user)->create(['parent_id' => null]);

        return Category::factory()->for($this->user)->create(['parent_id' => $parent->id]);
    }

    /**
     * Items with the same category, no tags, and no comment are merged.
     */
    public function test_merges_items_with_same_category_no_tags_no_comment(): void
    {
        $transaction = $this->createStandardTransaction();
        $transaction->transactionItems()->delete();

        $category = $this->createCategory();

        $transaction->transactionItems()->createMany([
            ['category_id' => $category->id, 'amount' => 10.00, 'comment' => null],
            ['category_id' => $category->id, 'amount' => 20.00, 'comment' => null],
            ['category_id' => $category->id, 'amount' => 5.00, 'comment' => null],
        ]);

        $removed = $this->service->mergeTransactionItems($transaction);

        $transaction->refresh()->load('transactionItems');
        $this->assertEquals(2, $removed);
        $this->assertCount(1, $transaction->transactionItems);
        $this->assertMoneyEquals(35.00, $transaction->transactionItems->first()->amount);
    }

    /**
     * Items with the same category and the same tags are merged.
     */
    public function test_merges_items_with_same_category_same_tags(): void
    {
        $transaction = $this->createStandardTransaction();
        $transaction->transactionItems()->delete();

        $category = $this->createCategory();
        $tag = Tag::factory()->for($this->user)->create();

        $item1 = $transaction->transactionItems()->create([
            'category_id' => $category->id,
            'amount' => 15.00,
            'comment' => null,
        ]);
        $item1->tags()->attach($tag);

        $item2 = $transaction->transactionItems()->create([
            'category_id' => $category->id,
            'amount' => 25.00,
            'comment' => null,
        ]);
        $item2->tags()->attach($tag);

        $removed = $this->service->mergeTransactionItems($transaction);

        $transaction->refresh()->load(['transactionItems', 'transactionItems.tags']);
        $this->assertEquals(1, $removed);
        $this->assertCount(1, $transaction->transactionItems);
        $this->assertMoneyEquals(40.00, $transaction->transactionItems->first()->amount);
    }

    /**
     * Items with different categories are NOT merged.
     */
    public function test_does_not_merge_items_with_different_categories(): void
    {
        $transaction = $this->createStandardTransaction();
        $transaction->transactionItems()->delete();

        $category1 = $this->createCategory();
        $category2 = $this->createCategory();

        $transaction->transactionItems()->createMany([
            ['category_id' => $category1->id, 'amount' => 10.00, 'comment' => null],
            ['category_id' => $category2->id, 'amount' => 20.00, 'comment' => null],
        ]);

        $removed = $this->service->mergeTransactionItems($transaction);

        $this->assertEquals(0, $removed);
        $this->assertCount(2, $transaction->fresh()->transactionItems);
    }

    /**
     * Items with a non-empty comment are NOT merged.
     */
    public function test_does_not_merge_items_with_comment(): void
    {
        $transaction = $this->createStandardTransaction();
        $transaction->transactionItems()->delete();

        $category = $this->createCategory();

        $transaction->transactionItems()->createMany([
            ['category_id' => $category->id, 'amount' => 10.00, 'comment' => 'note'],
            ['category_id' => $category->id, 'amount' => 20.00, 'comment' => null],
        ]);

        $removed = $this->service->mergeTransactionItems($transaction);

        $this->assertEquals(0, $removed);
        $this->assertCount(2, $transaction->fresh()->transactionItems);
    }

    /**
     * Items with different tags are NOT merged.
     */
    public function test_does_not_merge_items_with_different_tags(): void
    {
        $transaction = $this->createStandardTransaction();
        $transaction->transactionItems()->delete();

        $category = $this->createCategory();
        $tag1 = Tag::factory()->for($this->user)->create();
        $tag2 = Tag::factory()->for($this->user)->create();

        $item1 = $transaction->transactionItems()->create([
            'category_id' => $category->id,
            'amount' => 10.00,
            'comment' => null,
        ]);
        $item1->tags()->attach($tag1);

        $item2 = $transaction->transactionItems()->create([
            'category_id' => $category->id,
            'amount' => 20.00,
            'comment' => null,
        ]);
        $item2->tags()->attach($tag2);

        $removed = $this->service->mergeTransactionItems($transaction);

        $this->assertEquals(0, $removed);
        $this->assertCount(2, $transaction->fresh()->transactionItems);
    }

    /**
     * mergeIfEnabled skips when the user setting is disabled.
     */
    public function test_merge_if_enabled_skips_when_setting_is_disabled(): void
    {
        $user = User::factory()->create([
            'auto_merge_standard_transaction_items' => false,
        ]);
        $transaction = Transaction::factory()
            ->withdrawal($user)
            ->create(['user_id' => $user->id, 'schedule' => false, 'budget' => false]);
        $transaction->transactionItems()->delete();

        $category = $this->createCategory();
        $transaction->transactionItems()->createMany([
            ['category_id' => $category->id, 'amount' => 10.00, 'comment' => null],
            ['category_id' => $category->id, 'amount' => 20.00, 'comment' => null],
        ]);

        $this->service->mergeIfEnabled($transaction);

        $this->assertCount(2, $transaction->fresh()->transactionItems);
    }

    /**
     * mergeIfEnabled runs merge when the user setting is enabled.
     */
    public function test_merge_if_enabled_merges_when_setting_is_enabled(): void
    {
        $transaction = $this->createStandardTransaction();
        $transaction->transactionItems()->delete();

        $category = $this->createCategory();
        $transaction->transactionItems()->createMany([
            ['category_id' => $category->id, 'amount' => 10.00, 'comment' => null],
            ['category_id' => $category->id, 'amount' => 20.00, 'comment' => null],
        ]);

        $this->service->mergeIfEnabled($transaction);

        $transaction->refresh()->load('transactionItems');
        $this->assertCount(1, $transaction->transactionItems);
        $this->assertMoneyEquals(30.00, $transaction->transactionItems->first()->amount);
    }

    /**
     * Schedule transactions are not merged (even if setting is enabled).
     */
    public function test_skips_schedule_transactions(): void
    {
        $transaction = Transaction::factory()
            ->withdrawal($this->user)
            ->create(['user_id' => $this->user->id, 'schedule' => true, 'budget' => false]);
        $transaction->transactionItems()->delete();

        $category = $this->createCategory();
        $transaction->transactionItems()->createMany([
            ['category_id' => $category->id, 'amount' => 10.00, 'comment' => null],
            ['category_id' => $category->id, 'amount' => 20.00, 'comment' => null],
        ]);

        $removed = $this->service->mergeTransactionItems($transaction);

        $this->assertEquals(0, $removed);
        $this->assertCount(2, $transaction->fresh()->transactionItems);
    }

    /**
     * Mixed scenario: some items can be merged, others cannot.
     */
    public function test_partially_merges_where_possible(): void
    {
        $transaction = $this->createStandardTransaction();
        $transaction->transactionItems()->delete();

        $category1 = $this->createCategory();
        $category2 = $this->createCategory();

        // Two items for category1 (can merge) + one for category2 (stays)
        $transaction->transactionItems()->createMany([
            ['category_id' => $category1->id, 'amount' => 10.00, 'comment' => null],
            ['category_id' => $category1->id, 'amount' => 5.00, 'comment' => null],
            ['category_id' => $category2->id, 'amount' => 7.00, 'comment' => null],
        ]);

        $removed = $this->service->mergeTransactionItems($transaction);

        $transaction->refresh()->load('transactionItems');
        $this->assertEquals(1, $removed);
        $this->assertCount(2, $transaction->transactionItems);

        $mergedItem = $transaction->transactionItems->firstWhere('category_id', $category1->id);
        $this->assertMoneyEquals(15.00, $mergedItem->amount);
    }

    /**
     * The bccomp-based sum is exact for values that are notorious for IEEE-754 float
     * drift (0.1 + 0.2 !== 0.3 in binary float), unlike the old epsilon-tolerant check.
     */
    public function test_raw_amount_sum_is_exact_for_float_prone_values(): void
    {
        $transaction = $this->createStandardTransaction();
        $transaction->transactionItems()->delete();

        $category = $this->createCategory();
        $transaction->transactionItems()->createMany([
            ['category_id' => $category->id, 'amount' => 0.10, 'comment' => null],
            ['category_id' => $category->id, 'amount' => 0.20, 'comment' => null],
        ]);
        $transaction->refresh()->load('transactionItems');

        $sum = $this->sumRawAmounts($transaction->transactionItems);

        $this->assertSame('0.3000', $sum);
        $this->assertSame(0, bccomp($sum, '0.3', 4));
    }

    /**
     * The bccomp-based comparison correctly rejects a genuine amount mismatch,
     * preserving the safety guarantee the old epsilon check provided.
     */
    public function test_raw_amount_sum_detects_a_genuine_mismatch(): void
    {
        $transaction = $this->createStandardTransaction();
        $transaction->transactionItems()->delete();

        $category = $this->createCategory();
        $transaction->transactionItems()->createMany([
            ['category_id' => $category->id, 'amount' => 10.00, 'comment' => null],
            ['category_id' => $category->id, 'amount' => 20.00, 'comment' => null],
        ]);
        $transaction->refresh()->load('transactionItems');

        $fullSum = $this->sumRawAmounts($transaction->transactionItems);
        $partialSum = $this->sumRawAmounts($transaction->transactionItems->take(1));

        $this->assertNotSame(0, bccomp($fullSum, $partialSum, 4));
    }

    /**
     * transaction_items.amount is now a Money instance (MoneyCast); compare its exact
     * decimal amount rather than relying on PHP's loose float equality.
     */
    private function assertMoneyEquals(float $expected, Money $actual): void
    {
        $this->assertSame(number_format($expected, 4, '.', ''), (string) $actual->getAmount());
    }

    /**
     * Invoke the service's private raw-amount summation helper via reflection.
     */
    private function sumRawAmounts(iterable $items): string
    {
        $method = new ReflectionMethod(TransactionItemMergeService::class, 'sumRawAmounts');
        $method->setAccessible(true);

        return $method->invoke($this->service, $items);
    }
}
