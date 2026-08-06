<?php

namespace Tests\Feature\Console;

use App\Models\AccountEntity;
use App\Models\Budget;
use App\Models\Category;
use App\Models\Currency;
use App\Models\Payee;
use App\Models\Transaction;
use App\Models\TransactionSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\PendingCommand;
use RuntimeException;
use Tests\TestCase;

/**
 * Integration coverage for the budget/schedule redesign's transforming + schema migrations
 * (2026_07_26_000002_transform_budget_transactions_to_budgets.php and
 * 2026_07_26_000003_drop_budget_column_and_enforce_account_not_null.php), specification.md
 * Section 7.1/7.2/10.
 *
 * Each test rolls the schema back to just before those two migrations (restoring the `budget`
 * column and nullable account_from_id/account_to_id), seeds legacy-shaped data directly via
 * property assignment that bypasses mass-assignment guarding (Eloquent's Transaction model no
 * longer allows *creating* this shape through normal means at all — that is the point of this
 * redesign), then re-runs `migrate` and asserts the outcome.
 *
 * Uses DatabaseMigrations (full migrate:fresh per test), not RefreshDatabase: DDL statements
 * (the rollback/re-migrate this test performs) cause an implicit commit in MySQL, which would
 * silently break RefreshDatabase's transaction-per-test isolation.
 */
class BudgetMigrationTest extends TestCase
{
    use DatabaseMigrations;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('migrate:rollback', ['--step' => 2]);

        $this->user = User::factory()->create();

        Currency::factory()
            ->for($this->user)
            ->fromIsoCodes(['USD'])
            ->create(['base' => true]);
    }

    private function reapplyMigrations(): PendingCommand|int
    {
        return $this->artisan('migrate', ['--force' => true]);
    }

    private function createPayeeEntity(User $user): AccountEntity
    {
        return AccountEntity::factory()
            ->for($user)
            ->for(Payee::factory()->withUser($user), 'config')
            ->create();
    }

    /**
     * Marks an already-created transaction as the legacy "budget-only fake transaction" shape
     * (schedule=false, budget=true) via direct property assignment, since `budget` is no longer
     * mass-assignable (or even present in Transaction's PHPDoc/casts) post-redesign.
     */
    private function markBudgetOnly(Transaction $transaction): Transaction
    {
        $transaction->schedule = false;
        $transaction->budget = true;
        $transaction->saveQuietly();

        return $transaction->fresh();
    }

    // --- 7.1 guard: refuses to run while any risk case is present ---------------------------

    public function test_guard_blocks_migration_when_zero_item_budget_transaction_exists(): void
    {
        $transaction = $this->markBudgetOnly(
            Transaction::factory()->deposit($this->user)->create(['user_id' => $this->user->id])
        );
        $transaction->transactionItems()->delete();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Budget/schedule redesign migration blocked');

        $this->reapplyMigrations();
    }

    public function test_guard_blocks_migration_when_payee_attributed_budget_transaction_exists(): void
    {
        // Withdrawal: account_from_id is normally the real account side.
        $transaction = $this->markBudgetOnly(
            Transaction::factory()->withdrawal($this->user)->create(['user_id' => $this->user->id])
        );

        // Simulate legacy bad data: the "account" side is actually a payee.
        $payeeEntity = $this->createPayeeEntity($this->user);
        $transaction->config->update(['account_from_id' => $payeeEntity->id]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Budget/schedule redesign migration blocked');

        $this->reapplyMigrations();
    }

    public function test_guard_blocks_migration_when_stray_transfer_flagged_as_budget_exists(): void
    {
        $this->markBudgetOnly(
            Transaction::factory()->transfer($this->user)->create(['user_id' => $this->user->id])
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Budget/schedule redesign migration blocked');

        $this->reapplyMigrations();
    }

    public function test_guard_blocks_migration_when_stray_investment_flagged_as_budget_exists(): void
    {
        $this->markBudgetOnly(
            Transaction::factory()->buy($this->user)->create(['user_id' => $this->user->id])
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Budget/schedule redesign migration blocked');

        $this->reapplyMigrations();
    }

    public function test_guard_blocks_migration_when_currency_mismatch_exists(): void
    {
        $transaction = $this->markBudgetOnly(
            Transaction::factory()->deposit($this->user)->create(['user_id' => $this->user->id])
        );

        $otherCurrency = Currency::factory()
            ->for($this->user)
            ->fromIsoCodes(['EUR'])
            ->create(['base' => null]);

        $transaction->forceFill(['currency_id' => $otherCurrency->id])->save();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Budget/schedule redesign migration blocked');

        $this->reapplyMigrations();
    }

    public function test_guard_allows_migration_when_data_is_clean(): void
    {
        $category = Category::factory()->for($this->user)->create();
        $transaction = $this->markBudgetOnly(
            Transaction::factory()->deposit($this->user)->create(['user_id' => $this->user->id])
        );
        $transaction->transactionItems()->firstOrFail()->update(['category_id' => $category->id]);
        TransactionSchedule::factory()->for($transaction)->create();

        $this->reapplyMigrations()->assertSuccessful();

        $this->assertFalse(Schema::hasColumn('transactions', 'budget'));
    }

    // --- 7.2 transform: converts and hard-deletes ---------------------------------------------

    public function test_converts_budget_only_transaction_to_budget_row_and_hard_deletes_source(): void
    {
        $category = Category::factory()->for($this->user)->create();
        $transaction = $this->markBudgetOnly(
            Transaction::factory()->withdrawal($this->user)->create(['user_id' => $this->user->id])
        );
        $transaction->transactionItems()->delete();
        $transaction->transactionItems()->create([
            'category_id' => $category->id,
            'amount' => 42.50,
            'comment' => null,
        ]);
        $accountId = $transaction->config->account_from_id;
        TransactionSchedule::factory()->for($transaction)->create([
            'frequency' => 'MONTHLY',
            'interval' => 2,
            'start_date' => '2025-01-15',
            'end_date' => '2025-12-31',
            'count' => 6,
            'inflation' => 3.5,
        ]);

        $this->reapplyMigrations()->assertSuccessful();

        // Hard-deleted, not soft-deleted: the row is gone entirely.
        $this->assertDatabaseMissing('transactions', ['id' => $transaction->id]);
        $this->assertDatabaseCount('transaction_schedules', 0);
        $this->assertDatabaseCount('transaction_items', 0);

        $budget = Budget::where('user_id', $this->user->id)->sole();
        $this->assertSame($category->id, $budget->category_id);
        $this->assertSame($accountId, $budget->account_id);
        $this->assertSame('withdrawal', $budget->transaction_type->value);
        $this->assertEqualsWithDelta(42.50, $budget->amount, 0.0001);
        $this->assertSame('MONTHLY', $budget->frequency);
        $this->assertSame(2, $budget->interval);
        $this->assertSame('2025-01-15', $budget->start_date->toDateString());
        $this->assertSame('2025-12-31', $budget->end_date->toDateString());
        $this->assertSame(6, $budget->count);
        $this->assertEqualsWithDelta(3.5, $budget->inflation, 0.0001);
    }

    public function test_sums_amounts_per_distinct_category_when_converting(): void
    {
        $categoryA = Category::factory()->for($this->user)->create();
        $categoryB = Category::factory()->for($this->user)->create();
        $transaction = $this->markBudgetOnly(
            Transaction::factory()->withdrawal($this->user)->create(['user_id' => $this->user->id])
        );
        $transaction->transactionItems()->delete();
        $transaction->transactionItems()->createMany([
            ['category_id' => $categoryA->id, 'amount' => 10.00, 'comment' => null],
            ['category_id' => $categoryA->id, 'amount' => 15.00, 'comment' => null],
            ['category_id' => $categoryB->id, 'amount' => 20.00, 'comment' => null],
        ]);
        TransactionSchedule::factory()->for($transaction)->create();

        $this->reapplyMigrations()->assertSuccessful();

        $this->assertDatabaseMissing('transactions', ['id' => $transaction->id]);

        $budgetA = Budget::where('category_id', $categoryA->id)->sole();
        $budgetB = Budget::where('category_id', $categoryB->id)->sole();
        $this->assertEqualsWithDelta(25.00, $budgetA->amount, 0.0001);
        $this->assertEqualsWithDelta(20.00, $budgetB->amount, 0.0001);
    }

    public function test_leaves_account_id_null_when_account_side_is_unset(): void
    {
        $category = Category::factory()->for($this->user)->create();
        // Withdrawal with no account_from_id set: account-agnostic, account_to_id remains a payee.
        $transaction = $this->markBudgetOnly(
            Transaction::factory()->withdrawal($this->user)->create(['user_id' => $this->user->id])
        );
        $transaction->config->update(['account_from_id' => null]);
        $transaction->transactionItems()->delete();
        $transaction->transactionItems()->create(['category_id' => $category->id, 'amount' => 10.00, 'comment' => null]);
        TransactionSchedule::factory()->for($transaction)->create();

        $this->reapplyMigrations()->assertSuccessful();

        $budget = Budget::where('user_id', $this->user->id)->sole();
        $this->assertNull($budget->account_id);
    }

    public function test_converts_legacy_schedule_and_budget_hybrid_with_no_account(): void
    {
        // schedule=true AND budget=true with both account sides null: could never have fired as a
        // real transaction (no account to record against) - functionally identical to a
        // schedule=false budget-only row, and converted the same way.
        $category = Category::factory()->for($this->user)->create();
        $transaction = Transaction::factory()
            ->withdrawal($this->user)
            ->create(['user_id' => $this->user->id]);
        $transaction->config->update(['account_from_id' => null, 'account_to_id' => null]);
        $transaction->transactionItems()->delete();
        $transaction->transactionItems()->create(['category_id' => $category->id, 'amount' => 10.00, 'comment' => null]);
        TransactionSchedule::factory()->for($transaction)->create();

        $transaction->schedule = true;
        $transaction->budget = true;
        $transaction->saveQuietly();

        $this->reapplyMigrations()->assertSuccessful();

        $this->assertDatabaseMissing('transactions', ['id' => $transaction->id]);
        $budget = Budget::where('user_id', $this->user->id)->sole();
        $this->assertSame($category->id, $budget->category_id);
        $this->assertNull($budget->account_id);
    }

    public function test_does_not_touch_genuine_schedule_transactions_with_real_accounts(): void
    {
        // schedule=true, budget=true, but with real accounts on both sides: left untouched per
        // Section 7.2 ("Transactions currently schedule=true: no data movement needed").
        $transaction = Transaction::factory()
            ->withdrawal_schedule($this->user)
            ->create(['user_id' => $this->user->id]);
        $transaction->budget = true;
        $transaction->saveQuietly();

        $this->reapplyMigrations()->assertSuccessful();

        $this->assertDatabaseHas('transactions', ['id' => $transaction->id]);
        $this->assertSame(0, Budget::count());
    }

    // --- schema tail: NOT NULL enforcement ------------------------------------------------------

    public function test_not_null_migration_fails_loudly_if_null_accounts_remain(): void
    {
        // Simulate the invariant somehow not holding (defensive: should be unreachable once 7.2
        // has run cleanly) by leaving a null-account standard row outside the budget=true scope
        // the transforming migration touches.
        $transaction = Transaction::factory()
            ->withdrawal($this->user)
            ->create(['user_id' => $this->user->id, 'schedule' => true]);
        $transaction->config->update(['account_from_id' => null]);
        TransactionSchedule::factory()->for($transaction)->create();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('still have a null account_from_id/account_to_id');

        $this->reapplyMigrations();
    }
}
