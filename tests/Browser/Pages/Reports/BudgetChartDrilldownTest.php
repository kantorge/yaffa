<?php

namespace Tests\Browser\Pages\Reports;

use App\Models\Budget;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * FR-7 coverage: the budget-chart drill-down table is driven directly by budgetChart()'s own
 * contributing-rows data, no longer by a separate getScheduledItems() request.
 */
class BudgetChartDrilldownTest extends DuskTestCase
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

    public function test_drilldown_table_lists_contributing_budget_rows(): void
    {
        $category = Category::factory()->for($this->user)->create(['name' => 'Drilldown Test Category']);

        Budget::factory()->create([
            'user_id' => $this->user->id,
            'category_id' => $category->id,
            'account_id' => null,
            'transaction_type' => 'withdrawal',
            'amount' => 321,
            'frequency' => 'MONTHLY',
            'interval' => 1,
            'start_date' => now()->subDay(),
            'end_date' => null,
            'count' => null,
        ]);

        /** @var Transaction $transaction */
        $transaction = Transaction::factory()
            ->for($this->user)
            ->withdrawal_schedule($this->user)
            ->create();
        $transaction->transactionItems()->delete();
        $transaction->transactionItems()->create(['category_id' => $category->id, 'amount' => 111]);
        $transaction->config()->update(['amount_from' => 111, 'amount_to' => 111]);
        $transaction->transactionSchedule->update([
            'start_date' => now()->subDay(),
            'next_date' => now()->addWeek(),
            'end_date' => null,
            'count' => null,
            'interval' => 1,
            'frequency' => 'MONTHLY',
        ]);

        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visitRoute('reports.budgetchart')
                ->waitFor('#chartdiv', 10)
                ->waitFor('#all', 10)
                ->click('#all')
                ->waitFor('#reload:not([disabled])', 10)
                ->click('#reload')
                ->waitUsing(
                    20,
                    200,
                    fn () => $browser->script("return Array.isArray(window.chart?.data) && window.chart.data.length > 0;")[0] === true
                )
                ->waitFor('#table tbody tr', 10)
                ->waitFor('#scheduleTable tbody tr', 10)
                ->screenshot('budgetchart-drilldown')
                ->assertSeeIn('#table', 'Drilldown Test Category')
                ->assertSeeIn('#table', 'No account')
                // Currency-formatted (shared toFormattedCurrency helper), so the exact
                // decimals/symbol depend on the seeded base currency's own precision settings -
                // only the numeric value itself is asserted here.
                ->assertSeeIn('#table', '321')
                // Cadence column, rendered via rrule.js's toText() (FR-5).
                ->assertSeeIn('#table', 'month')
                ->assertSeeIn('#scheduleTable', 'Drilldown Test Category')
                ->assertSeeIn('#scheduleTable', '111')
                ->assertSeeIn('#scheduleTable', 'month')
                ->click('#table [data-view-budget]')
                ->waitFor('#modal-budget-quickview.show', 10)
                ->screenshot('budgetchart-quickview')
                ->assertSeeIn('#modal-budget-quickview', 'Drilldown Test Category')
                ->assertSeeIn('#modal-budget-quickview', '321')
                ->assertSeeIn('#modal-budget-quickview', 'every month');
        });
    }
}
