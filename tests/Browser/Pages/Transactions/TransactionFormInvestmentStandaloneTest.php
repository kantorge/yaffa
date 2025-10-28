<?php

use App\Models\Transaction;
use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;
use Throwable;

uses(Tests\DuskTestCase::class);
beforeEach(function () {
    // Migrate and seed only once for this file
    if (!static::$migrationRun) {
        $this->artisan('migrate:fresh');
        $this->artisan('db:seed');
        static::$migrationRun = true;
    }

    $this->user = User::firstWhere('email', $this::USER_EMAIL);
});


test('user can load the investment transaction form', function () {
    $this->actingAs($this->user);

        $browser = visit(route('transaction.create', ['type' => 'investment']))
        ->assertPresent(self::MAIN_FORM_SELECTOR);;
});

test('user cannot submit investment transaction form with errors', function () {
    $this->actingAs($this->user);

        $browser = visit(route('transaction.create', ['type' => 'investment']))
        // Try to save form without any data
        ->pressAndWaitFor(self::SUBMIT_BUTTON_SELECTOR)
        // The page should no have changed
        ->assertRouteIs('transaction.create', ['type' => 'investment'])
        // Error messages should be displayed in Bootstrap alert
        ->assertPresent('#transactionFormInvestment .alert.alert-danger');;
});

test('selecting an account limits investments to the same currency', function () {
    retry(3, function () {
        $this->actingAs($this->user);

            $browser = visit(route('transaction.create', ['type' => 'investment']))
            // Select account
            ->select2ExactSearch(self::ACCOUNT_DROPDOWN_SELECTOR, self::TEST_ACCOUNT_NAME_USD, 10)
            ->assertSeeIn(self::ACCOUNT_DROPDOWN_SELECTOR . ' + .select2', self::TEST_ACCOUNT_NAME_USD)
            // Make sure, that the account API call is finished, by waiting for the currency to be displayed
            ->waitForTextIn('@transaction-total-value', '$', 10)
            // Try to select an investment by opening the dropdown
            ->click(self::INVESTMENT_DROPDOWN_SELECTOR . ' + .select2')
            // Wait for the default options to load
            ->waitFor('.select2-container--open> .select2-dropdown > .select2-results > ul', 10)
            ->assertSeeIn(
                '.select2-container--open > .select2-dropdown > .select2-results > ul',
                self::TEST_INVESTMENT_NAME_USD
            )
            ->assertDontSeeIn(
                '.select2-container--open > .select2-dropdown > .select2-results > ul',
                self::TEST_INVESTMENT_NAME_EUR
            );;
    });
});

test('selecting an investment limits accounts to the same currency', function () {
    $this->actingAs($this->user);

        $browser = visit(route('transaction.create', ['type' => 'investment']))
        // As a preparation, select an investment with known currency
        ->select2ExactSearch(self::INVESTMENT_DROPDOWN_SELECTOR, self::TEST_INVESTMENT_NAME_USD, 10)
        ->assertSeeIn(self::INVESTMENT_DROPDOWN_SELECTOR . ' + .select2', self::TEST_INVESTMENT_NAME_USD)
        // As a main test, search for accounts
        ->click(self::ACCOUNT_DROPDOWN_SELECTOR . ' + .select2')
        ->waitFor('span.select2-container--open:not(.select2-container--below)')
        // Search for investment accounts, without specifying a currency
        ->type('.select2-search__field', 'Investment account')
        // Wait for results to load, which means that the Searing text is not displayed anymore
        ->waitUntilMissing('#select2-account-results ul.select2-results__options li.select2-results__option.loading-results', 10)
        // Verify that only accounts with USD currency are displayed
        ->waitForTextIn('.select2-container--open > .select2-dropdown > .select2-results > ul', self::TEST_ACCOUNT_NAME_USD, 10)
        ->assertDontSeeIn('.select2-container--open > .select2-dropdown > .select2-results > ul', self::TEST_ACCOUNT_NAME_EUR);;
});

test('currency displayed correctly for various settings 1', function () {
    $browser->loginAs($this->user)
        // Open transaction investment form
        ->visitRoute('transaction.create', ['type' => 'investment'])
        ->assertPresent(self::MAIN_FORM_SELECTOR)

        // Select account
        ->select2ExactSearch(self::ACCOUNT_DROPDOWN_SELECTOR, self::TEST_ACCOUNT_NAME_USD, 10)

        // Validate currency is displayed correctly
        ->waitForTextIn('@transaction-total-value', '$')

        // Remove the account
        ->select2ClearAll(self::ACCOUNT_DROPDOWN_SELECTOR, '#transactionFormInvestment')

        // Validate that no currency is displayed
        ->assertNotPresent('@transaction-total-value')

        // Select investment
        ->select2ExactSearch(self::INVESTMENT_DROPDOWN_SELECTOR, self::TEST_INVESTMENT_NAME_EUR, 10)

        // Validate currency is displayed correctly
        ->waitForTextIn('@transaction-total-value', '€')

        // Remove the investment
        ->select2ClearAll(self::INVESTMENT_DROPDOWN_SELECTOR, '#transactionFormInvestment')

        // Validate that no currency is displayed
        ->assertNotPresent('@transaction-total-value');;
});

test('currency displayed correctly for various settings 2', function () {
    $browser->loginAs($this->user)
        // Open transaction investment form
        ->visitRoute('transaction.create', ['type' => 'investment'])
        ->assertPresent(self::MAIN_FORM_SELECTOR)

        // Select investment
        ->select2ExactSearch(self::INVESTMENT_DROPDOWN_SELECTOR, self::TEST_INVESTMENT_NAME_EUR, 10)

        // Validate currency is displayed correctly
        ->waitForTextIn('@transaction-total-value', '€')

        // Remove the investment
        ->select2ClearAll(self::INVESTMENT_DROPDOWN_SELECTOR, '#transactionFormInvestment')

        // Validate that no currency is displayed
        ->assertNotPresent('@transaction-total-value')

        // Select account
        ->select2ExactSearch(self::ACCOUNT_DROPDOWN_SELECTOR, self::TEST_ACCOUNT_NAME_USD, 10)

        // Validate currency is displayed correctly
        ->waitForTextIn('@transaction-total-value', '$')

        // Remove the account
        ->select2ClearAll(self::ACCOUNT_DROPDOWN_SELECTOR, '#transactionFormInvestment')

        // Validate that no currency is displayed
        ->assertNotPresent('@transaction-total-value');;
});

test('user can submit buy transaction form', function () {
    $this->actingAs($this->user);

    // Fill form
    fillStandardBuyForm($browser)
        // Verify that dividend field is disabled
        ->assertDisabled('#transaction_dividend')
        // Submit form
        ->clickAndWaitForReload(self::SUBMIT_BUTTON_SELECTOR)
        // A success message should be available in a Vue component
        ->waitForTextIn('#BootstrapNotificationContainer', 'Transaction added', 10);;
});

test('user can submit sell transaction form', function () {
    $this->actingAs($this->user);

        $browser = visit(route('transaction.create', ['type' => 'investment']))
        // Select account
        ->select2ExactSearch(self::ACCOUNT_DROPDOWN_SELECTOR, self::TEST_ACCOUNT_NAME_USD, 10)
        // Select investment
        ->select2ExactSearch(self::INVESTMENT_DROPDOWN_SELECTOR, self::TEST_INVESTMENT_NAME_USD, 10)
        // Select type
        ->select('#transaction_type', 'Sell')
        // Verify that dividend field is disabled
        ->assertDisabled('#transaction_dividend')
        // Add quantity
        ->type('#transaction_quantity', '10')
        // Add price
        ->type('#transaction_price', '20')
        // Add commission
        ->type('#transaction_commission', '30')
        // Add taxes
        ->type('#transaction_tax', '40')
        // Submit form
        ->clickAndWaitForReload(self::SUBMIT_BUTTON_SELECTOR)
        // A success message should be available in a Vue component
        ->waitForTextIn('#BootstrapNotificationContainer', 'Transaction added', 10);;
});

test('user can submit dividend transaction form', function () {
    $this->actingAs($this->user);

        $browser = visit(route('transaction.create', ['type' => 'investment']))
        // Select account
        ->select2ExactSearch(self::ACCOUNT_DROPDOWN_SELECTOR, self::TEST_ACCOUNT_NAME_USD, 10)
        // Select investment
        ->select2ExactSearch(self::INVESTMENT_DROPDOWN_SELECTOR, self::TEST_INVESTMENT_NAME_USD, 10)
        // Select type
        ->select('#transaction_type', 'Dividend')
        // Verify that quantity field is disabled
        ->assertDisabled('#transaction_quantity')
        // Verify that price field is disabled
        ->assertDisabled('#transaction_price')
        // Add dividend
        ->type('#transaction_dividend', '1000')
        // Add commission
        ->type('#transaction_commission', '30')
        // Add taxes
        ->type('#transaction_tax', '40')
        // Submit form
        ->clickAndWaitForReload(self::SUBMIT_BUTTON_SELECTOR, 10)
        // A success message should be available in a Vue component
        ->waitForTextIn('#BootstrapNotificationContainer', 'Transaction added', 10);;
});

/**
 * User can submit add shares transaction form.
 * An existing user can visit the investment transaction form and submit an add shares transaction,
 * if all required data is provided.
 *
 * @tag transaction, transction form, investment, add shares
 * @throws Throwable
 */
test('user can submit add shares transaction form', function () {
    retry(3, function () {
        $this->actingAs($this->user);

            $browser = visit(route('transaction.create', ['type' => 'investment']))
            // Select account
            ->select2ExactSearch(self::ACCOUNT_DROPDOWN_SELECTOR, self::TEST_ACCOUNT_NAME_USD, 10)
            // Select investment
            ->select2ExactSearch(self::INVESTMENT_DROPDOWN_SELECTOR, self::TEST_INVESTMENT_NAME_USD, 10)
            // Select type
            ->select('#transaction_type', 'Add shares')
            // Verify that price field is disabled
            ->assertDisabled('#transaction_price')
            // Add quantity
            ->type('#transaction_quantity', '10')
            // Add commission
            ->type('#transaction_commission', '30')
            // Add taxes
            ->type('#transaction_tax', '40')
            // Verify that dividend field is disabled
            ->assertDisabled('#transaction_dividend')
            // Submit form
            ->clickAndWaitForReload(self::SUBMIT_BUTTON_SELECTOR)
            // A success message should be available in a Vue component
            ->waitForTextIn('#BootstrapNotificationContainer', 'Transaction added', 10);;
    });
});

test('user can submit transaction with schedule', function () {
    $this->markTestIncomplete('Not implemented yet.');
});

test('callback add an other transaction', function () {
    $this->actingAs($this->user);
    fillStandardBuyForm($browser)
        ->click('button[value="create"]')
        // Submit form
        ->clickAndWaitForReload(self::SUBMIT_BUTTON_SELECTOR)
        ->assertRouteIs('transaction.create', ['type' => 'investment']);;
});

test('callback clone transaction', function () {
    $this->actingAs($this->user);
    fillStandardBuyForm($browser)
        ->click('button[value="clone"]')
        // Submit form
        ->clickAndWaitForReload(self::SUBMIT_BUTTON_SELECTOR);

    // Get the last transaction from the database
    $transaction = Transaction::orderByDesc('id')->first();

    // Check that the view is the view is the transaction clone
    $browser->assertRouteIs(
        'transaction.open',
        [
            'action' => 'clone',
            'transaction' => $transaction->id
        ]
    );;
});

test('callback show transaction', function () {
    $this->actingAs($this->user);
    fillStandardBuyForm($browser)
        ->click('button[value="show"]')
        // Submit form
        ->clickAndWaitForReload(self::SUBMIT_BUTTON_SELECTOR);

    // Get the last transaction from the database
    $transaction = Transaction::orderByDesc('id')->first();

    // Check that the view is the view is the transaction clone
    $browser->assertRouteIs(
        'transaction.open',
        [
            'action' => 'show',
            'transaction' => $transaction->id
        ]
    );;
});

test('callback return to selected account', function () {
    $this->actingAs($this->user);
    fillStandardBuyForm($browser)
        ->click('button[value="returnToPrimaryAccount"]')
        // Submit form
        ->clickAndWaitForReload(self::SUBMIT_BUTTON_SELECTOR);

    // Get the last transaction from the database
    $transaction = Transaction::orderByDesc('id')
        ->with(['config'])
        ->first();

    $browser->assertRouteIs(
        'account-entity.show',
        ['account_entity' => $transaction->config->account_id]
    );;
});

test('callback return to selected investment', function () {
    $this->markTestIncomplete('This function is not implemented yet');
});

test('callback return to dashboard', function () {
    $this->actingAs($this->user);
    fillStandardBuyForm($browser)
        ->click('button[value="returnToDashboard"]')
        // Submit form
        ->clickAndWaitForReload(self::SUBMIT_BUTTON_SELECTOR);

    $browser->assertRouteIs('home');;
});

test('callback return to previous page', function () {
    $this->actingAs($this->user);

        $browser = visit(route('tag.index'));
    fillStandardBuyForm($browser)
        ->click('button[value="back"]')
        ->clickAndWaitForReload(self::SUBMIT_BUTTON_SELECTOR)
        ->assertRouteIs('tag.index');;
});

test('user can change the date on the investment form', function () {
    $this->actingAs($this->user);

    // Fill form with standard data
    fillStandardBuyForm($browser)
        // Click the date field to open the date picker
        ->click('#date')
        // Wait for the date picker to open
        ->waitFor('.vc-pane-container', 10)
        // Click the first day of the previous month, which is in the first column
        // (This is to avoid clicking the current day on the 1st of the month, which would remove the date)
        ->click('.vc-pane-container .vc-pane.column-1 .vc-day.in-month')
        // Wait for the date picker to close
        ->waitUntilMissing('.vc-pane-container', 10)
        // Select callback to show the transaction
        ->click('@action-after-save-desktop-button-group button[value="show"]')
        // Submit form
        ->clickAndWaitForReload(self::SUBMIT_BUTTON_SELECTOR);

    // Get the last transaction from the database
    $transaction = Transaction::orderByDesc('id')->first();

    // Confirm that the transaction date is the first day of the previous month
    $this->assertEquals(
        now()->subMonthNoOverflow()->startOfMonth()->format('Y-m-d'),
        $transaction->date->format('Y-m-d')
    );;

});

// Helpers
function fillStandardBuyForm(Browser $browser): Browser
{
    return retry(3, fn () => $browser
        ->visitRoute('transaction.create', ['type' => 'investment'])
            // Wait for the form and key elements to be present
        ->waitFor(self::MAIN_FORM_SELECTOR)
        ->waitFor(self::ACCOUNT_DROPDOWN_SELECTOR, 10)
        ->waitFor(self::INVESTMENT_DROPDOWN_SELECTOR, 10)
            // Select type
        ->select('#transaction_type', 'Buy')
            // Add quantity
        ->type('#transaction_quantity', '10')
            // Add price
        ->type('#transaction_price', '20')
            // Add commission
        ->type('#transaction_commission', '30')
            // Add taxes
        ->type('#transaction_tax', '40')
            // Select account
        ->select2ExactSearch(self::ACCOUNT_DROPDOWN_SELECTOR, self::TEST_ACCOUNT_NAME_USD, 10)
            // Select investment
        ->select2ExactSearch(self::INVESTMENT_DROPDOWN_SELECTOR, self::TEST_INVESTMENT_NAME_USD, 10));
}
