<?php

namespace Tests\Feature\Console;

use App\Console\Commands\CheckBudgetMigration;
use App\Models\AccountEntity;
use App\Models\Currency;
use App\Models\Payee;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckBudgetMigrationCommandTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        Currency::factory()
            ->for($this->user)
            ->fromIsoCodes(['USD'])
            ->create(['base' => true]);
    }

    /**
     * Creates an AccountEntity of type payee for the given user.
     */
    private function createPayeeEntity(User $user): AccountEntity
    {
        return AccountEntity::factory()
            ->for($user)
            ->for(Payee::factory()->withUser($user), 'config')
            ->create();
    }

    public function test_passes_with_no_issues_for_clean_budget_only_transaction(): void
    {
        Transaction::factory()
            ->deposit($this->user)
            ->create([
                'user_id' => $this->user->id,
                'schedule' => false,
                'budget' => true,
            ]);

        $this->artisan(CheckBudgetMigration::class)
            ->assertSuccessful();
    }

    public function test_detects_budget_only_transaction_with_zero_items(): void
    {
        $transaction = Transaction::factory()
            ->deposit($this->user)
            ->create([
                'user_id' => $this->user->id,
                'schedule' => false,
                'budget' => true,
            ]);

        $transaction->transactionItems()->delete();

        $this->artisan(CheckBudgetMigration::class)
            ->expectsOutputToContain("Budget-only transactions with zero transaction items: 1 found")
            ->expectsOutputToContain((string) $transaction->id)
            ->assertFailed();
    }

    public function test_detects_budget_only_transaction_attributed_to_payee(): void
    {
        // Withdrawal: account_from_id is normally the real account side.
        $transaction = Transaction::factory()
            ->withdrawal($this->user)
            ->create([
                'user_id' => $this->user->id,
                'schedule' => false,
                'budget' => true,
            ]);

        // Simulate legacy bad data: the "account" side is actually a payee.
        $payeeEntity = $this->createPayeeEntity($this->user);
        $transaction->config->update(['account_from_id' => $payeeEntity->id]);

        $this->artisan(CheckBudgetMigration::class)
            ->expectsOutputToContain('Budget-only transactions attributed to a payee instead of a real account: 1 found')
            ->expectsOutputToContain((string) $transaction->id)
            ->assertFailed();
    }

    public function test_detects_stray_transfer_with_budget_flag(): void
    {
        $transaction = Transaction::factory()
            ->transfer($this->user)
            ->create([
                'user_id' => $this->user->id,
                'schedule' => false,
                'budget' => true,
            ]);

        $this->artisan(CheckBudgetMigration::class)
            ->expectsOutputToContain('Transfer/investment transactions incorrectly flagged as budget: 1 found')
            ->expectsOutputToContain((string) $transaction->id)
            ->assertFailed();
    }

    public function test_detects_stray_investment_with_budget_flag(): void
    {
        $transaction = Transaction::factory()
            ->buy($this->user)
            ->create([
                'user_id' => $this->user->id,
                'schedule' => false,
                'budget' => true,
            ]);

        $this->artisan(CheckBudgetMigration::class)
            ->expectsOutputToContain('Transfer/investment transactions incorrectly flagged as budget: 1 found')
            ->expectsOutputToContain((string) $transaction->id)
            ->assertFailed();
    }

    public function test_detects_currency_mismatch_against_linked_account(): void
    {
        $transaction = Transaction::factory()
            ->deposit($this->user)
            ->create([
                'user_id' => $this->user->id,
                'schedule' => false,
                'budget' => true,
            ]);

        $otherCurrency = Currency::factory()
            ->for($this->user)
            ->fromIsoCodes(['EUR'])
            ->create(['base' => null]);

        // Force a mismatch against the linked account's actual currency (currency_id is not fillable).
        $transaction->forceFill(['currency_id' => $otherCurrency->id])->save();

        $this->artisan(CheckBudgetMigration::class)
            ->expectsOutputToContain("Budget-only transactions whose currency doesn't match their linked account (or base currency): 1 found")
            ->expectsOutputToContain((string) $transaction->id)
            ->assertFailed();
    }

    public function test_detects_currency_mismatch_against_base_currency_when_account_agnostic(): void
    {
        $transaction = Transaction::factory()
            ->withdrawal($this->user)
            ->create([
                'user_id' => $this->user->id,
                'schedule' => false,
                'budget' => true,
            ]);

        // Simulate an account-agnostic budget-only transaction: the account side is unset.
        $transaction->config->update(['account_from_id' => null]);

        $otherCurrency = Currency::factory()
            ->for($this->user)
            ->fromIsoCodes(['EUR'])
            ->create(['base' => null]);

        $transaction->forceFill(['currency_id' => $otherCurrency->id])->save();

        $this->artisan(CheckBudgetMigration::class)
            ->expectsOutputToContain("Budget-only transactions whose currency doesn't match their linked account (or base currency): 1 found")
            ->expectsOutputToContain('Budget-only transactions attributed to a payee instead of a real account: 0 found')
            ->assertFailed();
    }

    public function test_does_not_flag_null_currency_id_as_mismatch_for_account_agnostic_budget(): void
    {
        $transaction = Transaction::factory()
            ->withdrawal($this->user)
            ->create([
                'user_id' => $this->user->id,
                'schedule' => false,
                'budget' => true,
            ]);

        // An account-agnostic budget-only transaction: no linked account, and currency_id
        // is null — the same documented "use the base currency" state
        // Transaction::getTransactionCurrencyAttribute() already falls back to, not an error.
        $transaction->config->update(['account_from_id' => null]);
        $transaction->forceFill(['currency_id' => null])->save();

        $this->artisan(CheckBudgetMigration::class)
            ->expectsOutputToContain("Budget-only transactions whose currency doesn't match their linked account (or base currency): 0 found")
            ->assertSuccessful();
    }

    public function test_does_not_flag_transactions_outside_budget_only_scope(): void
    {
        // A regular, non-schedule, non-budget transaction is irrelevant to this audit.
        Transaction::factory()
            ->deposit($this->user)
            ->create([
                'user_id' => $this->user->id,
                'schedule' => false,
                'budget' => false,
            ]);

        // A schedule=true, budget=true transaction with a real account on both sides is
        // already handled and out of scope - only the accountless legacy hybrid (see the
        // tests below) is in scope.
        Transaction::factory()
            ->deposit($this->user)
            ->create([
                'user_id' => $this->user->id,
                'schedule' => true,
                'budget' => true,
            ]);

        $this->artisan(CheckBudgetMigration::class)
            ->assertSuccessful();
    }

    public function test_passes_with_no_issues_for_clean_legacy_schedule_budget_hybrid_with_no_account(): void
    {
        // The narrow legacy case budgetOnlyQuery() also covers: schedule=true, budget=true,
        // with no real account on either side - functionally indistinguishable from a
        // schedule=false budget-only row, and equally in scope for the transforming migration.
        $transaction = Transaction::factory()
            ->deposit($this->user)
            ->create([
                'user_id' => $this->user->id,
                'schedule' => true,
                'budget' => true,
            ]);

        $transaction->config->update(['account_from_id' => null, 'account_to_id' => null]);

        $this->artisan(CheckBudgetMigration::class)
            ->assertSuccessful();
    }

    public function test_detects_zero_item_transaction_for_legacy_schedule_budget_hybrid_with_no_account(): void
    {
        $transaction = Transaction::factory()
            ->deposit($this->user)
            ->create([
                'user_id' => $this->user->id,
                'schedule' => true,
                'budget' => true,
            ]);

        $transaction->config->update(['account_from_id' => null, 'account_to_id' => null]);
        $transaction->transactionItems()->delete();

        $this->artisan(CheckBudgetMigration::class)
            ->expectsOutputToContain('Budget-only transactions with zero transaction items: 1 found')
            ->expectsOutputToContain((string) $transaction->id)
            ->assertFailed();
    }
}
