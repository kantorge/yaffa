<?php

namespace Tests\Browser\Pages\Reports\FindTransactions;

use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class FindTransactionsFilterBehaviorTest extends DuskTestCase
{
    protected static bool $migrationRun = false;

    protected User $user;

    public function setUp(): void
    {
        parent::setUp();

        // Migrate and seed only once for this file
        if (!static::$migrationRun) {
            $this->artisan('migrate:fresh');
            $this->artisan('db:seed');
            static::$migrationRun = true;
        }

        $this->user = User::firstWhere('email', $this::USER_EMAIL);
    }
    public function test_date_selector_defaults_are_loaded_from_the_url()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visitRoute('reports.transactions', ['date_from' => '2022-01-01', 'date_to' => '2022-01-31'])
                ->waitFor('#dateRangePicker')
                ->assertInputValue('#date_from', '2022-01-01')
                ->assertInputValue('#date_to', '2022-01-31');

            $browser->visitRoute('reports.transactions', [])
                ->waitFor('#dateRangePicker')
                ->assertInputValue('#date_from', '')
                ->assertInputValue('#date_to', '');
        });
    }

    public function test_date_selector_preset_selections_are_respected()
    {
        $this->browse(function(Browser $browser) {
            $browser->loginAs($this->user)
                ->visitRoute('reports.transactions')
                ->waitFor('#dateRangePicker')
                // Select option with value "thisMonth"
                ->select('#dateRangePickerPresets', 'thisMonth')
                ->assertInputValue('#date_from', date('Y-m-01'))
                ->assertInputValue('#date_to', date('Y-m-t'))
                // Check the parameters in the URL
                ->assertQueryStringHas('date_from', date('Y-m-01'))
                ->assertQueryStringHas('date_to', date('Y-m-t'))
                // Remove the selection with the option "placeholder"
                ->select('#dateRangePickerPresets', 'placeholder')
                ->assertInputValue('#date_from', '')
                ->assertInputValue('#date_to', '')
                // Check the parameters in the URL
                ->assertQueryStringMissing('date_from')
                ->assertQueryStringMissing('date_to');
        });
    }

    public function test_date_selector_clear_button_behavior()
    {
        $this->browse(function(Browser $browser) {
            $browser->loginAs($this->user)
                ->visitRoute('reports.transactions')
                ->waitFor('#dateRangePicker')
                // Set the date range using the presets
                ->select('#dateRangePickerPresets', 'thisMonth')
                ->assertInputValue('#date_from', date('Y-m-01'))
                ->assertInputValue('#date_to', date('Y-m-t'))
                // Clear the date range
                ->click('#clearDateSelection')
                ->assertInputValue('#date_from', '')
                ->assertInputValue('#date_to', '')
                ->assertSelected('#dateRangePickerPresets', 'placeholder');
        });
    }
}
