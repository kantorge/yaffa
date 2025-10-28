<?php

use App\Models\Investment;
use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

uses(Tests\DuskTestCase::class);

const TABLESELECTOR = '#table';
beforeEach(function () {
    // Migrate and seed only once for this file
    if (!static::$migrationRun) {
        $this->artisan('migrate:fresh');
        $this->artisan('db:seed');
        static::$migrationRun = true;
    }
});


test('user can load and manage investment prices', function () {
    // Load the main test user
    $user = User::firstWhere('email', $this::USER_EMAIL);

    // Create a new investment for the user; the investment group and currency are already created
    $investment = Investment::factory()
        ->for($user)
        ->create();

    $browser
        // Acting as the main user
        ->loginAs($user)
        // Load the investment prices list
        ->visitRoute('investment-price.list', $investment)
        // Wait for the table to load
        ->waitFor('@table-investment-prices')
        // Check that the investment prices list is visible
        ->assertPresent('@table-investment-prices');

    // The table should be empty, as no investment prices are created yet
    $this->assertEquals(
        0,
        $this->getTableRowCount($browser, TABLESELECTOR)
    );

    // The user can open the create investment price page with the related form
    $browser
        ->click('@button-add-investment-price')
        ->waitFor('@form-investment-price')
        ->assertPresent('@form-investment-price')
        // Click the date field, and select today's date from the date picker
        ->click('@input-date')
        ->waitFor('div.datepicker')
        ->click('div.datepicker button.today-button')
        // Fill in the price field
        ->type('@input-price', 100)
        // Submit the form
        ->click('@button-submit')
        // The page should navigate back to the investment prices list
        ->waitFor('@table-investment-prices')
        ->waitFor('#BootstrapNotificationContainer div.alert-success');

    // The table should now have one row
    $this->assertEquals(
        1,
        $this->getTableRowCount($browser, TABLESELECTOR)
    );

    // The user can open the edit investment price page with the related form
    $browser
        ->click(TABLESELECTOR . ' a.btn-primary[title="Edit"]')
        ->waitFor('@form-investment-price')
        ->assertPresent('@form-investment-price')
        // Change the price field
        ->type('@input-price', 200)
        // Submit the form
        ->click('@button-submit')
        // The page should navigate back to the investment prices list
        ->waitFor('@table-investment-prices')
        ->waitFor('#BootstrapNotificationContainer div.alert-success');

    // The table should still have one row
    $this->assertEquals(
        1,
        $this->getTableRowCount($browser, TABLESELECTOR)
    );

    // The user can delete the investment price
    $browser
        ->click(TABLESELECTOR . ' button.btn-danger[title="Delete"]')
        // Confirm the deletion
        ->acceptDialog()
        // The page should navigate back to the investment prices list
        ->waitFor('@table-investment-prices')
        ->waitFor('#BootstrapNotificationContainer div.alert-success');

    // The table should now be empty again
    $this->assertEquals(
        0,
        $this->getTableRowCount($browser, TABLESELECTOR)
    );;
});
