<?php

namespace Tests\Browser\Pages\InvestmentPrices;

use App\Models\Investment;
use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class InvestmentPriceTest extends DuskTestCase
{
    protected static bool $migrationRun = false;

    const TABLESELECTOR = '#table-investment-prices';

    protected function setUp(): void
    {
        parent::setUp();

        // Migrate and seed only once for this file
        if (!static::$migrationRun) {
            $this->artisan('migrate:fresh');
            $this->artisan('db:seed');
            static::$migrationRun = true;
        }
    }

    public function test_user_can_load_and_manage_investment_prices(): void
    {
        // Load the main test user
        $user = User::firstWhere('email', $this::USER_EMAIL);

        // Create a new investment for the user; the investment group and currency are already created
        $investment = Investment::factory()
            ->for($user)
            ->create();

        $this->browse(function (Browser $browser) use ($user, $investment) {
            // The investment prices list can be loaded for the investment
            $browser
                // Acting as the main user
                ->loginAs($user)
                // Load the investment prices list
                ->visitRoute('investment-price.list', $investment)
                // Wait for the table to load
                ->waitFor(self::TABLESELECTOR)
                // Check that the investment prices list is visible
                ->assertPresent(self::TABLESELECTOR);

            // The table should be empty, as no investment prices are created yet
            $this->assertEquals(
                0,
                $this->getTableRowCount($browser, self::TABLESELECTOR)
            );

            // The user can open the create investment price page with the related form
            $browser
                ->click('button.add-investment-price-button')
                // Modal dialog should become visible
                ->waitFor('#investmentPriceModal')
                // Add today's date to the date field using the native date picker
                // The test user is expected to have US locale, so the date picker opens in MM/DD/YYYY format
                ->type('#priceDate', date('m/d/Y'))
                // Fill in the price field
                ->type('#priceValue', 100)
                // Submit the form
                ->click('#priceSubmit')
                // The modal should be hidden, and a success toast shown
                ->waitUntilMissing('#investmentPriceModal')
                ->waitFor('div.toast-container .toast.bg-success');

            // The table should now have one row
            $this->assertEquals(
                1,
                $this->getTableRowCount($browser, self::TABLESELECTOR)
            );

            // The user can open the modal dialog to edit investment price
            $browser
                ->click(self::TABLESELECTOR . ' button.edit-price')
                ->waitFor('#investmentPriceModal')
                ->assertPresent('#investmentPriceModal')
                // Change the price field
                ->type('#priceValue', 200)
                // Submit the form
                ->click('#priceSubmit')
                // The modal should be hidden, and a success toast shown
                ->waitUntilMissing('#investmentPriceModal')
                ->waitFor('div.toast-container .toast.bg-success');

            // The table should still have one row
            $this->assertEquals(
                1,
                $this->getTableRowCount($browser, self::TABLESELECTOR)
            );

            // The user can delete the investment price
            $browser
                ->click(self::TABLESELECTOR . ' button.delete-price')
                // Confirm the deletion via the SweetAlert dialog
                ->waitFor('.swal2-container')
                ->click('.swal2-container button.swal2-confirm')
                // Wait for the success notification
                ->waitFor('div.toast-container .toast.bg-success')
                // Wait for the table row to actually be removed from the DOM - no assertion is made for the same check
                ->waitUntil('document.querySelectorAll("' . self::TABLESELECTOR . ' tbody tr:not(tr:has(td.dt-empty))").length === 0', 5);
        });
    }
}
