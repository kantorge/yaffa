<?php

namespace Tests\Browser\Pages\Reports;

use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class AmChartsLocalizationTest extends DuskTestCase
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

    public function test_budget_chart_uses_french_amcharts_locale_settings(): void
    {
        $this->user->update([
            'language' => 'fr',
            'locale' => 'fr-FR',
        ]);

        $this->browse(function (Browser $browser) {
            $browser
                ->loginAs($this->user)
                ->visitRoute('reports.budgetchart')
                ->waitFor('#chartdiv', 10)
                ->waitFor('#all', 10)
                ->click('#all')
                ->waitFor('#reload:not([disabled])', 10)
                ->click('#reload')
                ->waitUsing(
                    20,
                    200,
                    fn () =>
                    $browser->script("return Array.isArray(window.chart?.data) && window.chart.data.length > 0;")[0] === true
                )
                ->waitUsing(
                    20,
                    200,
                    fn () =>
                    $browser->script("return window.chart?.numberFormatter?.intlLocales === 'fr-FR';")[0] === true
                )
                ->assertScript("return window.chart.numberFormatter.intlLocales === 'fr-FR';", true);
        });
    }
}
