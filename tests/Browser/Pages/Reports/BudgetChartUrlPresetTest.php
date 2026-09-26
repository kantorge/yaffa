<?php

namespace Tests\Browser\Pages\Reports;

use App\Models\Budget;
use App\Models\Category;
use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * Regression coverage: a `?categories[0]=<id>` deep link (the indexed-array query-string form,
 * as opposed to `?categories[]=<id>`) must still preset the category tree and auto-trigger the
 * initial chart load - both forms are equivalent server-side, so the frontend reader must accept
 * both (see getArrayParamFromUrl in resources/js/shared/lib/helpers).
 */
class BudgetChartUrlPresetTest extends DuskTestCase
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

    public function test_indexed_bracket_category_param_autoloads_the_chart(): void
    {
        $category = Category::factory()->for($this->user)->create(['name' => 'URL Preset Category']);

        Budget::factory()->create([
            'user_id' => $this->user->id,
            'category_id' => $category->id,
            'account_id' => null,
            'transaction_type' => 'withdrawal',
            'amount' => 42,
            'frequency' => 'MONTHLY',
            'interval' => 1,
            'start_date' => now(),
            'end_date' => null,
            'count' => null,
        ]);

        $this->browse(function (Browser $browser) use ($category) {
            $browser->loginAs($this->user)
                ->visit('/reports/budgetchart?categories%5B0%5D=' . $category->id)
                ->waitFor('#chartdiv', 10)
                // No manual interaction - the preset must drive the initial load on its own.
                ->waitUsing(
                    20,
                    200,
                    fn () => $browser->script("return Array.isArray(window.chart?.data) && window.chart.data.length > 0;")[0] === true
                )
                ->waitFor('#table tbody tr', 10)
                ->assertSeeIn('#table', 'URL Preset Category');
        });
    }
}
