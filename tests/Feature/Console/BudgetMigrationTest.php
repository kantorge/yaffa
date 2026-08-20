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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

/**
 * Integration coverage for the budget/schedule redesign's transforming + schema migrations
 * (2026_08_05_000002_transform_budget_transactions_to_budgets.php and
 * 2026_08_05_000003_drop_budget_column_and_enforce_account_not_null.php), specification.md
 * Section 7.1/7.2/10.
 *
 * Each scenario rolls the schema back to just before those two migrations (restoring the
 * `budget` column and nullable account_from_id/account_to_id), seeds legacy-shaped data
 * directly via property assignment that bypasses mass-assignment guarding (Eloquent's
 * Transaction model no longer allows *creating* this shape through normal means at all — that
 * is the point of this redesign), then re-runs `migrate` and asserts the outcome.
 *
 * Uses DatabaseMigrations (full migrate:fresh once per test method), not RefreshDatabase: DDL
 * statements (the rollback/re-migrate each scenario performs) cause an implicit commit in
 * MySQL, which would silently break RefreshDatabase's transaction-per-test isolation. That
 * migrate:fresh replay of the entire migration history is the expensive part of this class (not
 * the targeted 2-step rollback/reapply each scenario does on top of it), so scenarios sharing
 * the same outcome shape (blocked / converted) are grouped into one test method each — one
 * migrate:fresh instead of one per scenario — while every individual assertion from the
 * original one-scenario-per-method version is preserved unchanged.
 */
class BudgetMigrationTest extends TestCase
{
    use DatabaseMigrations;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resetToPreMigrationBaseline();

        $this->user = User::factory()->create();

        Currency::factory()
            ->for($this->user)
            ->fromIsoCodes(['USD'])
            ->create(['base' => true]);
    }

    /**
     * Rolls back every migration after 2026_08_05_000001_create_budgets_table (the transform +
     * drop-column pair this test exercises), not a hardcoded --step=2 - a later release/v4
     * migration (e.g. 2026_08_08_..._price_scale) sorting after that pair would otherwise make
     * rollback target the wrong two migrations and silently skip the transform migration's
     * guard entirely. This exact class of breakage already happened once before during an
     * earlier rebase (see project memory / architecture.md) when the budget migrations' own
     * dates were bumped past a colliding one; computing the count instead of hardcoding it makes
     * this robust against it recurring a third time.
     *
     * Also used mid-test (not just from setUp()) to reset between scenarios within a single
     * consolidated test method: a scenario that reaches the guard exception never gets the
     * transform migration recorded as run (it throws before completing), so by the time this
     * runs again there may be nothing left above the baseline to roll back at all - guard against
     * that explicitly rather than passing `--step => 0`, which Laravel's migrate:rollback treats
     * as falsy/"unset" and silently falls back to rolling back the *entire last batch* instead of
     * doing nothing. migrate:fresh records every migration as one single batch, so that fallback
     * would blow straight through the intended baseline into irreversible pre-4.0 migrations.
     */
    private function resetToPreMigrationBaseline(): void
    {
        $migrationsToRollBack = DB::table('migrations')
            ->where('migration', '>', '2026_08_05_000001_create_budgets_table')
            ->count();

        if ($migrationsToRollBack > 0) {
            Artisan::call('migrate:rollback', ['--step' => $migrationsToRollBack]);
        }
    }

    private function reapplyMigrations(): int
    {
        return $this->artisan('migrate', ['--force' => true])->run();
    }

    /**
     * Runs `migrate` and asserts it throws a RuntimeException containing $expectedMessage,
     * for use inside a test method that checks several blocking scenarios in turn (can't use
     * expectException()/expectExceptionMessage() more than once per test method).
     */
    private function assertMigrationBlocked(string $expectedMessage): void
    {
        try {
            $this->reapplyMigrations();
            $this->fail("Expected migration to throw a RuntimeException containing \"{$expectedMessage}\", but it completed successfully.");
        } catch (RuntimeException $e) {
            $this->assertStringContainsString($expectedMessage, $e->getMessage());
        }
    }

    /**
     * A blocked migrate attempt throws before doing any data cleanup, so the offending
     * transaction (and its items/schedule/config) is still sitting in the table afterwards -
     * unlike the original one-scenario-per-test-method version, a single consolidated test
     * method reuses the same data across scenarios unless it's explicitly torn down, and the
     * guard's "N issue(s) found" count would otherwise keep growing across scenarios and leak
     * into unrelated ones (e.g. the NOT NULL tail scenario reporting the earlier scenarios'
     * still-present issues instead of its own). Goes through the model relationships rather
     * than raw table names since `config` is polymorphic (standard/transfer/investment each
     * have their own table).
     */
    private function deleteTransaction(Transaction $transaction): void
    {
        $transaction->transactionItems()->delete();
        $transaction->transactionSchedule()->delete();
        $transaction->config()->delete();
        $transaction->delete();
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

    // --- 7.1 guard + NOT NULL tail: refuses to run while any risk case is present -------------

    public function test_guard_blocks_migration_for_every_risky_data_shape(): void
    {
        // Zero-item budget-only transaction.
        $transaction = $this->markBudgetOnly(
            Transaction::factory()->deposit($this->user)->create(['user_id' => $this->user->id])
        );
        $transaction->transactionItems()->delete();
        $this->assertMigrationBlocked('Budget/schedule redesign migration blocked');
        $this->deleteTransaction($transaction);

        // Payee-attributed budget transaction: the "account" side is actually a payee.
        $this->resetToPreMigrationBaseline();
        $transaction = $this->markBudgetOnly(
            Transaction::factory()->withdrawal($this->user)->create(['user_id' => $this->user->id])
        );
        $payeeEntity = $this->createPayeeEntity($this->user);
        $transaction->config->update(['account_from_id' => $payeeEntity->id]);
        $this->assertMigrationBlocked('Budget/schedule redesign migration blocked');
        $this->deleteTransaction($transaction);

        // Stray transfer flagged as budget.
        $this->resetToPreMigrationBaseline();
        $transaction = $this->markBudgetOnly(
            Transaction::factory()->transfer($this->user)->create(['user_id' => $this->user->id])
        );
        $this->assertMigrationBlocked('Budget/schedule redesign migration blocked');
        $this->deleteTransaction($transaction);

        // Stray investment flagged as budget.
        $this->resetToPreMigrationBaseline();
        $transaction = $this->markBudgetOnly(
            Transaction::factory()->buy($this->user)->create(['user_id' => $this->user->id])
        );
        $this->assertMigrationBlocked('Budget/schedule redesign migration blocked');
        $this->deleteTransaction($transaction);

        // Currency mismatch.
        $this->resetToPreMigrationBaseline();
        $transaction = $this->markBudgetOnly(
            Transaction::factory()->deposit($this->user)->create(['user_id' => $this->user->id])
        );
        $otherCurrency = Currency::factory()
            ->for($this->user)
            ->fromIsoCodes(['EUR'])
            ->create(['base' => null]);
        $transaction->forceFill(['currency_id' => $otherCurrency->id])->save();
        $this->assertMigrationBlocked('Budget/schedule redesign migration blocked');
        $this->deleteTransaction($transaction);

        // Schema-tail NOT NULL enforcement: defensive check for an invariant that should be
        // unreachable once 7.2 has run cleanly - a null-account standard row outside the
        // budget=true scope the transforming migration touches.
        $this->resetToPreMigrationBaseline();
        $transaction = Transaction::factory()
            ->withdrawal($this->user)
            ->create(['user_id' => $this->user->id, 'schedule' => true]);
        $transaction->config->update(['account_from_id' => null]);
        TransactionSchedule::factory()->for($transaction)->create();
        $this->assertMigrationBlocked('still have a null account_from_id/account_to_id');
    }

    /**
     * Each converted scenario's resulting Budget row(s) are deleted before the next scenario
     * runs (`Budget::where('user_id', ...)->delete()` between blocks below) for the same reason
     * deleteTransaction() exists on the blocked-scenario side: the transform migration's down()
     * doesn't reconstruct the original transactions from Budget rows (this is a one-way data
     * transformation, see the class docblock), so leftover Budget rows from an earlier scenario
     * in this method would otherwise still be there when a later scenario's `->sole()` query
     * expects to find only its own.
     */
    public function test_guard_allows_and_converts_clean_data_correctly(): void
    {
        // Guard allows migration when data is clean, and the budget column is actually dropped.
        $category = Category::factory()->for($this->user)->create();
        $transaction = $this->markBudgetOnly(
            Transaction::factory()->deposit($this->user)->create(['user_id' => $this->user->id])
        );
        $transaction->transactionItems()->firstOrFail()->update(['category_id' => $category->id]);
        TransactionSchedule::factory()->for($transaction)->create();

        $this->assertSame(0, $this->reapplyMigrations());
        $this->assertFalse(Schema::hasColumn('transactions', 'budget'));
        // This scenario is itself budget-only shaped (schedule=false, budget=true with a
        // category), so the transform migration converts it too - clean up before the next
        // scenario's Budget::sole() lookup expects to find only its own row.
        Budget::where('user_id', $this->user->id)->delete();

        // Converts a budget-only transaction to a Budget row and hard-deletes the source.
        $this->resetToPreMigrationBaseline();
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

        $this->assertSame(0, $this->reapplyMigrations());

        // Hard-deleted, not soft-deleted: the row is gone entirely.
        $this->assertDatabaseMissing('transactions', ['id' => $transaction->id]);
        $this->assertDatabaseCount('transaction_schedules', 0);
        $this->assertDatabaseCount('transaction_items', 0);

        $budget = Budget::where('user_id', $this->user->id)->sole();
        $this->assertSame($category->id, $budget->category_id);
        $this->assertSame($accountId, $budget->account_id);
        $this->assertSame('withdrawal', $budget->transaction_type->value);
        $this->assertEqualsWithDelta(42.50, $budget->amount->getAmount()->toFloat(), 0.0001);
        $this->assertSame('MONTHLY', $budget->frequency);
        $this->assertSame(2, $budget->interval);
        $this->assertSame('2025-01-15', $budget->start_date->toDateString());
        $this->assertSame('2025-12-31', $budget->end_date->toDateString());
        $this->assertSame(6, $budget->count);
        $this->assertEqualsWithDelta(3.5, $budget->inflation, 0.0001);
        Budget::where('user_id', $this->user->id)->delete();

        // Sums amounts per distinct category when converting.
        $this->resetToPreMigrationBaseline();
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

        $this->assertSame(0, $this->reapplyMigrations());

        $this->assertDatabaseMissing('transactions', ['id' => $transaction->id]);
        $budgetA = Budget::where('category_id', $categoryA->id)->sole();
        $budgetB = Budget::where('category_id', $categoryB->id)->sole();
        $this->assertEqualsWithDelta(25.00, $budgetA->amount->getAmount()->toFloat(), 0.0001);
        $this->assertEqualsWithDelta(20.00, $budgetB->amount->getAmount()->toFloat(), 0.0001);
        Budget::where('user_id', $this->user->id)->delete();

        // Leaves account_id null when the account side is unset (account-agnostic withdrawal,
        // account_to_id remains a payee).
        $this->resetToPreMigrationBaseline();
        $category = Category::factory()->for($this->user)->create();
        $transaction = $this->markBudgetOnly(
            Transaction::factory()->withdrawal($this->user)->create(['user_id' => $this->user->id])
        );
        $transaction->config->update(['account_from_id' => null]);
        $transaction->transactionItems()->delete();
        $transaction->transactionItems()->create(['category_id' => $category->id, 'amount' => 10.00, 'comment' => null]);
        TransactionSchedule::factory()->for($transaction)->create();

        $this->assertSame(0, $this->reapplyMigrations());

        $budget = Budget::where('user_id', $this->user->id)->sole();
        $this->assertNull($budget->account_id);
        Budget::where('user_id', $this->user->id)->delete();

        // Converts a legacy schedule=true AND budget=true hybrid with no account on either side:
        // could never have fired as a real transaction, converted the same way as a
        // schedule=false budget-only row.
        $this->resetToPreMigrationBaseline();
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

        $this->assertSame(0, $this->reapplyMigrations());

        $this->assertDatabaseMissing('transactions', ['id' => $transaction->id]);
        $budget = Budget::where('user_id', $this->user->id)->sole();
        $this->assertSame($category->id, $budget->category_id);
        $this->assertNull($budget->account_id);
    }

    public function test_does_not_touch_genuine_schedule_transactions_with_real_accounts(): void
    {
        // schedule=true, budget=true, but with real accounts on both sides: left untouched per
        // Section 7.2 ("Transactions currently schedule=true: no data movement needed"). Kept as
        // its own test method (rather than folded into the conversion cluster above) since it's
        // the one scenario asserting nothing was converted, and mixing that into a method full of
        // "assert a Budget row was created" checks would make a future regression easy to misread.
        $transaction = Transaction::factory()
            ->withdrawal_schedule($this->user)
            ->create(['user_id' => $this->user->id]);
        $transaction->budget = true;
        $transaction->saveQuietly();

        $this->assertSame(0, $this->reapplyMigrations());

        $this->assertDatabaseHas('transactions', ['id' => $transaction->id]);
        $this->assertSame(0, Budget::count());
    }
}
