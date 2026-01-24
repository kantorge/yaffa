<?php

namespace Tests\Browser\Pages\Transactions;

use App\Models\Transaction;
use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;
use Throwable;

class TransactionFormInvestmentStandaloneTest extends DuskTestCase
{
    protected static bool $migrationRun = false;

    private const string ACCOUNT_DROPDOWN_SELECTOR = '#account';
    private const string INVESTMENT_DROPDOWN_SELECTOR = '#investment';
    private const string MAIN_FORM_SELECTOR = '#transactionFormInvestment';
    private const string SUBMIT_BUTTON_SELECTOR = '#transactionFormInvestment-Save';
    private const string TEST_INVESTMENT_NAME_USD = 'Test investment USD';
    private const string TEST_INVESTMENT_NAME_EUR = 'Test investment EUR';
    private const string TEST_ACCOUNT_NAME_USD = 'Investment account USD';
    private const string TEST_ACCOUNT_NAME_EUR = 'Investment account EUR';

    protected User $user;

    protected function setUp(): void
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

    private function fillStandardBuyForm(Browser $browser): Browser
    {
        return retry(3, fn() => $browser
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

    public function test_user_can_load_the_investment_transaction_form(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visitRoute('transaction.create', ['type' => 'investment'])
                ->waitFor(self::MAIN_FORM_SELECTOR)
                ->assertPresent(self::MAIN_FORM_SELECTOR);
        });
    }

    public function test_user_cannot_submit_investment_transaction_form_with_errors(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visitRoute('transaction.create', ['type' => 'investment'])
                ->waitFor(self::MAIN_FORM_SELECTOR)

                // Try to save form without any data
                ->pressAndWaitFor(self::SUBMIT_BUTTON_SELECTOR)
                // The page should no have changed
                ->assertRouteIs('transaction.create', ['type' => 'investment'])
                // Error messages should be displayed in Bootstrap alert
                ->assertPresent('#transactionFormInvestment .alert.alert-danger');
        });
    }

    public function test_selecting_an_account_limits_investments_to_the_same_currency(): void
    {
        retry(3, function () {
            $this->browse(function (Browser $browser) {
                $browser->loginAs($this->user)
                    ->visitRoute('transaction.create', ['type' => 'investment'])
                    ->waitFor(self::MAIN_FORM_SELECTOR)

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
                    );
            });
        });
    }

    public function test_selecting_an_investment_limits_accounts_to_the_same_currency(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visitRoute('transaction.create', ['type' => 'investment'])
                ->waitFor(self::MAIN_FORM_SELECTOR)

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
                ->assertDontSeeIn('.select2-container--open > .select2-dropdown > .select2-results > ul', self::TEST_ACCOUNT_NAME_EUR);
        });
    }

    public function test_currency_displayed_correctly_for_various_settings_1(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                // Open transaction investment form
                ->visitRoute('transaction.create', ['type' => 'investment'])
                ->waitFor(self::MAIN_FORM_SELECTOR)

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
                ->assertNotPresent('@transaction-total-value');
        });
    }

    public function test_currency_displayed_correctly_for_various_settings_2(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                // Open transaction investment form
                ->visitRoute('transaction.create', ['type' => 'investment'])
                ->waitFor(self::MAIN_FORM_SELECTOR)

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
                ->assertNotPresent('@transaction-total-value');
        });
    }

    public function test_user_can_submit_buy_transaction_form(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user);

            // Fill form
            $this->fillStandardBuyForm($browser)
                // Verify that dividend field is disabled
                ->assertDisabled('#transaction_dividend')
                // Submit form
                ->clickAndWaitForReload(self::SUBMIT_BUTTON_SELECTOR)
                // A success message should be available in a Vue component
                ->waitForTextIn('#BootstrapNotificationContainer', 'Transaction added', 10);
        });
    }

    public function test_user_can_submit_sell_transaction_form(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visitRoute('transaction.create', ['type' => 'investment'])
                ->waitFor(
                    self::MAIN_FORM_SELECTOR
                )
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
                ->waitForTextIn('#BootstrapNotificationContainer', 'Transaction added', 10);
        });
    }

    public function test_user_can_submit_dividend_transaction_form(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visitRoute('transaction.create', ['type' => 'investment'])
                ->waitFor(self::MAIN_FORM_SELECTOR)

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
                ->waitForTextIn('#BootstrapNotificationContainer', 'Transaction added', 10);
        });
    }

    /**
     * User can submit add shares transaction form.
     * An existing user can visit the investment transaction form and submit an add shares transaction,
     * if all required data is provided.
     *
     * @tag transaction, transction form, investment, add shares
     * @throws Throwable
     */
    public function test_user_can_submit_add_shares_transaction_form(): void
    {
        retry(3, function () {
            $this->browse(function (Browser $browser) {
                $browser->loginAs($this->user)
                    ->visitRoute('transaction.create', ['type' => 'investment'])
                    ->waitFor(self::MAIN_FORM_SELECTOR)

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
                    ->waitForTextIn('#BootstrapNotificationContainer', 'Transaction added', 10);
            });
        });
    }

    public function test_user_can_submit_transaction_with_schedule(): void
    {
        $this->markTestIncomplete('Not implemented yet.');
    }

    public function test_callback_add_an_other_transaction(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user);
            $this->fillStandardBuyForm($browser)
                ->click('button[value="create"]')
                // Submit form
                ->clickAndWaitForReload(self::SUBMIT_BUTTON_SELECTOR)
                ->assertRouteIs('transaction.create', ['type' => 'investment']);
        });
    }

    public function test_callback_clone_transaction(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user);
            $this->fillStandardBuyForm($browser)
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
            );
        });
    }

    public function test_callback_show_transaction(): void
    {
        $this->browse(function (Browser $browser) {
            retry(3, fn() => $browser
                ->loginAs($this->user)
                ->assertAuthenticatedAs($this->user)
            );
            $this->fillStandardBuyForm($browser)
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
            );
        });
    }

    public function test_callback_return_to_selected_account(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user);
            $this->fillStandardBuyForm($browser)
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
            );
        });
    }

    public function test_callback_return_to_selected_investment(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user);
            $this->fillStandardBuyForm($browser)
                ->click('button[value="returnToInvestment"]')
                // Submit form
                ->clickAndWaitForReload(self::SUBMIT_BUTTON_SELECTOR);

            // Get the last transaction from the database
            $transaction = Transaction::orderByDesc('id')
                ->with(['config'])
                ->first();

            $browser->assertRouteIs(
                'investment.show',
                ['investment' => $transaction->config->investment_id]
            );
        });
    }

    public function test_callback_return_to_dashboard(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user);
            $this->fillStandardBuyForm($browser)
                ->click('button[value="returnToDashboard"]')
                // Submit form
                ->clickAndWaitForReload(self::SUBMIT_BUTTON_SELECTOR);

            $browser->assertRouteIs('home');
        });
    }

    public function test_callback_return_to_previous_page(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visitRoute('tag.index');
            $this->fillStandardBuyForm($browser)
                ->click('button[value="back"]')
                ->clickAndWaitForReload(self::SUBMIT_BUTTON_SELECTOR)
                ->assertRouteIs('tag.index');
        });
    }

    public function test_user_can_change_the_date_on_the_investment_form(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user);

            // Fill form with standard data
            $this->fillStandardBuyForm($browser)
                // Click the date field to open the date picker
                ->click('#investment-date')
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
            );
        });

    }

    public function test_default_values_are_loaded_from_url(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visitRoute(
                    'transaction.create',
                    [
                        'type' => 'investment',
                        'account' => $this->user->accounts()->firstWhere('name', self::TEST_ACCOUNT_NAME_USD)->id,
                        'investment' => $this->user->investments()->firstWhere('name', self::TEST_INVESTMENT_NAME_USD)->id,
                    ]
                )
                // Wait for the form and the Select2 elements to load
                ->waitFor(self::MAIN_FORM_SELECTOR)
                ->waitFor(self::ACCOUNT_DROPDOWN_SELECTOR . ' + .select2 span.select2-selection__rendered', 10)
                ->waitFor(self::INVESTMENT_DROPDOWN_SELECTOR . ' + .select2 span.select2-selection__rendered', 10)

                // Verify that the account is selected
                ->assertSeeIn(
                    self::ACCOUNT_DROPDOWN_SELECTOR . ' + .select2 span.select2-selection__rendered',
                    self::TEST_ACCOUNT_NAME_USD
                )

                // Verify that the investment is selected
                ->assertSeeIn(
                    self::INVESTMENT_DROPDOWN_SELECTOR . ' + .select2 span.select2-selection__rendered',
                    self::TEST_INVESTMENT_NAME_USD
                );
        });
    }
}
