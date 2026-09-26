<?php

namespace Tests\Browser\Pages\Reports;

use App\Models\Budget;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * FR-6 coverage: the schedules report page shows both real schedules and standalone Budgets in
 * one merged table, the row-type filter works, and the Budget create/edit modal functions.
 */
class ScheduleBudgetMergedPageTest extends DuskTestCase
{
    protected static bool $migrationRun = false;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        if (!static::$migrationRun) {
            $this->artisan('migrate:fresh');
            $this->artisan('db:seed');
            static::$migrationRun = true;
        }

        $this->user = User::firstWhere('email', $this::USER_EMAIL);
    }

    public function test_merged_listing_shows_schedule_and_budget_rows_with_row_type_filter(): void
    {
        /** @var Transaction $transaction */
        $transaction = Transaction::factory()
            ->for($this->user)
            ->withdrawal_schedule($this->user)
            ->create();
        $transaction->transactionSchedule->update([
            'start_date' => now()->subDay(),
            'next_date' => now()->addWeek(),
            'end_date' => null,
            'count' => null,
            'interval' => 1,
            'frequency' => 'MONTHLY',
        ]);

        $category = Category::factory()->for($this->user)->create(['name' => 'Dusk Test Groceries']);
        $budget = Budget::factory()->create([
            'user_id' => $this->user->id,
            'category_id' => $category->id,
            'account_id' => null,
            'transaction_type' => 'withdrawal',
            'amount' => 250,
            'frequency' => 'MONTHLY',
            'interval' => 1,
            'start_date' => now()->subDay(),
            'end_date' => null,
            'count' => null,
        ]);

        $this->browse(function (Browser $browser) use ($transaction, $budget) {
            $browser->loginAs($this->user)
                ->visitRoute('report.schedules')
                ->waitUntilMissing('#table .dataTables_empty', 10)
                ->waitFor('#table tbody tr[data-id="' . $transaction->id . '"][data-row-type="schedule"]', 10)
                ->waitFor('#table tbody tr[data-id="' . $budget->id . '"][data-row-type="budget"]', 10)
                ->screenshot('schedule-budget-merged-listing')
                // The old per-row "Schedule" boolean filter/column is gone; the row-type
                // (Schedule/Budget) filter replaces the old plain "Budget" boolean filter.
                ->assertMissing('[dusk="button-group-table-filter-schedule"]')
                ->assertPresent('[dusk="button-group-table-filter-row-type"]')
                ->assertPresent('#button-new-budget')
                ->click('#button-new-budget')
                ->waitFor('#newBudgetModal.show', 10)
                ->screenshot('budget-new-modal')
                ->assertVisible('#newBudgetModal-category_id')
                ->assertVisible('#newBudgetModal-account_id')
                ->assertVisible('#newBudgetModal-transaction_type_withdrawal')
                ->assertVisible('#newBudgetModal-transaction_type_deposit')
                ->assertVisible('#newBudgetModal-amount')
                ->assertVisible('#newBudgetModal-comment');
        });
    }
}
