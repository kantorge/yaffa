<?php

namespace Tests\Feature\Console;

use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class MergeStandardTransactionItemsCommandTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'auto_merge_standard_transaction_items' => true,
        ]);

        $parent = Category::factory()->for($this->user)->create(['parent_id' => null]);
        $this->category = Category::factory()->for($this->user)->create(['parent_id' => $parent->id]);
    }

    /**
     * Creates a standard transaction with two mergeable items (same category, no comment).
     */
    private function createTransactionWithMergeableItems(User $user): Transaction
    {
        $transaction = Transaction::factory()
            ->withdrawal($user)
            ->create(['user_id' => $user->id, 'schedule' => false, 'budget' => false]);

        $transaction->transactionItems()->delete();
        $transaction->transactionItems()->createMany([
            ['category_id' => $this->category->id, 'amount' => 10.00, 'comment' => null],
            ['category_id' => $this->category->id, 'amount' => 20.00, 'comment' => null],
        ]);

        return $transaction;
    }

    /**
     * The command dispatches merge jobs for standard transactions that have mergeable items.
     */
    public function test_dispatches_jobs_for_all_standard_transactions(): void
    {
        Bus::fake();

        $this->createTransactionWithMergeableItems($this->user);

        $this->artisan('app:transactions:merge-standard-items')
            ->assertSuccessful();

        Bus::assertBatched(fn ($batch) => $batch->jobs->count() >= 1);
    }

    /**
     * The command accepts a userId argument and only processes that user's transactions.
     */
    public function test_accepts_user_id_argument(): void
    {
        Bus::fake();

        $otherUser = User::factory()->create(['auto_merge_standard_transaction_items' => true]);
        $otherParent = Category::factory()->for($otherUser)->create(['parent_id' => null]);
        $otherCategory = Category::factory()->for($otherUser)->create(['parent_id' => $otherParent->id]);

        $this->createTransactionWithMergeableItems($this->user);

        $otherTransaction = Transaction::factory()
            ->withdrawal($otherUser)
            ->create(['user_id' => $otherUser->id, 'schedule' => false, 'budget' => false]);
        $otherTransaction->transactionItems()->delete();
        $otherTransaction->transactionItems()->createMany([
            ['category_id' => $otherCategory->id, 'amount' => 10.00, 'comment' => null],
            ['category_id' => $otherCategory->id, 'amount' => 20.00, 'comment' => null],
        ]);

        $this->artisan('app:transactions:merge-standard-items', ['userId' => $this->user->id])
            ->assertSuccessful();

        // Should have dispatched a batch with exactly 1 job (only for $this->user)
        Bus::assertBatched(fn ($batch) => $batch->jobs->count() === 1);
    }

    /**
     * The command skips schedule and budget transactions.
     */
    public function test_skips_schedule_and_budget_transactions(): void
    {
        Bus::fake();

        Transaction::factory()
            ->withdrawal($this->user)
            ->create(['user_id' => $this->user->id, 'schedule' => true, 'budget' => false]);

        Transaction::factory()
            ->withdrawal($this->user)
            ->create(['user_id' => $this->user->id, 'schedule' => false, 'budget' => true]);

        $this->artisan('app:transactions:merge-standard-items', ['userId' => $this->user->id])
            ->assertSuccessful();

        Bus::assertNothingBatched();
    }

    /**
     * The command skips transactions that have no mergeable duplicate items.
     */
    public function test_skips_transactions_without_mergeable_items(): void
    {
        Bus::fake();

        $parent = Category::factory()->for($this->user)->create(['parent_id' => null]);
        $category2 = Category::factory()->for($this->user)->create(['parent_id' => $parent->id]);

        // Transaction with only a single item — nothing to merge.
        $singleItem = Transaction::factory()
            ->withdrawal($this->user)
            ->create(['user_id' => $this->user->id, 'schedule' => false, 'budget' => false]);
        $singleItem->transactionItems()->delete();
        $singleItem->transactionItems()->create([
            'category_id' => $this->category->id,
            'amount' => 10.00,
            'comment' => null,
        ]);

        // Transaction with two items in different categories — no mergeable group.
        $differentCategories = Transaction::factory()
            ->withdrawal($this->user)
            ->create(['user_id' => $this->user->id, 'schedule' => false, 'budget' => false]);
        $differentCategories->transactionItems()->delete();
        $differentCategories->transactionItems()->createMany([
            ['category_id' => $this->category->id, 'amount' => 10.00, 'comment' => null],
            ['category_id' => $category2->id, 'amount' => 20.00, 'comment' => null],
        ]);

        // Transaction with two same-category items but both have a comment — not mergeable.
        $withComments = Transaction::factory()
            ->withdrawal($this->user)
            ->create(['user_id' => $this->user->id, 'schedule' => false, 'budget' => false]);
        $withComments->transactionItems()->delete();
        $withComments->transactionItems()->createMany([
            ['category_id' => $this->category->id, 'amount' => 10.00, 'comment' => 'note a'],
            ['category_id' => $this->category->id, 'amount' => 20.00, 'comment' => 'note b'],
        ]);

        $this->artisan('app:transactions:merge-standard-items', ['userId' => $this->user->id])
            ->assertSuccessful();

        Bus::assertNothingBatched();
    }

    /**
     * The command fails gracefully with an invalid userId.
     */
    public function test_fails_with_invalid_user_id(): void
    {
        $this->artisan('app:transactions:merge-standard-items', ['userId' => 9999])
            ->assertFailed();
    }
}
