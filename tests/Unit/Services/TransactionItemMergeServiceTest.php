<?php

namespace Tests\Unit\Services;

use App\Models\Category;
use App\Models\Tag;
use App\Models\Transaction;
use App\Models\User;
use App\Services\TransactionItemMergeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $this->assertEquals(35.00, $transaction->transactionItems->first()->amount);
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
        $this->assertEquals(40.00, $transaction->transactionItems->first()->amount);
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
        $this->assertEquals(30.00, $transaction->transactionItems->first()->amount);
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
        $this->assertEquals(15.00, $mergedItem->amount);
    }
}
