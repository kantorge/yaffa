<?php

namespace Tests\Browser\Pages\Reports;

use App\Models\Budget;
use App\Models\Category;
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
                ->screenshot('budgetchart-drilldown')
                ->assertSeeIn('#table', 'Drilldown Test Category')
                ->assertSeeIn('#table', 'No account')
                ->assertSeeIn('#table', '321.00');
        });
    }
}
