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

        $this->user = User::firstWhere('email', 'demo@yaffa.cc');
    }

    private function fillStandardBuyForm(Browser $browser): Browser
    {
        return $browser
            ->visitRoute('transaction.create', ['type' => 'investment'])
            // Wait for form to load
            ->waitFor('#transactionFormInvestment')
            // Select account
            ->select2ExactSearch('#account', 'Investment account USD', 10)
            // Select investment
            ->select2ExactSearch('#investment', 'Test investment USD', 10)
            // Select type
            ->select('#transaction_type', 'Buy')
            // Add quantity
            ->type('#transaction_quantity', '10')
            // Add price
            ->type('#transaction_price', '20')
            // Add commission
            ->type('#transaction_commission', '30')
            // Add taxes
            ->type('#transaction_tax', '40');
    }

    public function test_user_can_load_the_investment_transaction_form()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visitRoute('transaction.create', ['type' => 'investment'])
                ->assertPresent('#transactionFormInvestment');
        });
    }

    public function test_user_cannot_submit_investment_transaction_form_with_errors()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visitRoute('transaction.create', ['type' => 'investment'])
                // Try to save form without any data
                ->pressAndWaitFor('#transactionFormInvestment-Save')
                // The page should no have changed
                ->assertRouteIs('transaction.create', ['type' => 'investment'])
                // Error messages should be displayed in Bootstrap alert
                ->assertPresent('#transactionFormInvestment .alert.alert-danger');
        });
    }

    public function test_selecting_an_account_limits_investments_to_the_same_currency()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visitRoute('transaction.create', ['type' => 'investment'])
                // Select account
                ->select2ExactSearch('#account', 'Investment account USD', 10)
                ->assertSeeIn('#account + .select2', 'Investment account USD')
                // Try to select an investment
                ->click('#investment + .select2')
                ->waitFor('.select2-container--open')
                ->assertSeeIn('.select2-container--open > .select2-dropdown > .select2-results > ul', 'Test investment USD')
                ->assertDontSeeIn('.select2-container--open > .select2-dropdown > .select2-results > ul', 'Test investment EUR');
        });
    }

    public function test_selecting_an_investment_limits_accounts_to_the_same_currency()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visitRoute('transaction.create', ['type' => 'investment'])
                // Select investment
                ->select2ExactSearch('#investment', 'Test investment USD', 10)
                ->assertSeeIn('#investment + .select2', 'Test investment USD')
                // Try to select an account
                ->click('#account + .select2')
                ->waitFor('.select2-container--open')
                // Search for investment accounts
                ->type('.select2-search__field', 'Investment account')
                // Wait for results to load
                ->waitFor('#select2-account-results .select2-results__option:not(.loading-results)', 10)
                // Verify that only accounts with USD currency are displayed
                ->assertSeeIn('.select2-container--open > .select2-dropdown > .select2-results > ul', 'Investment account USD')
                ->assertDontSeeIn('.select2-container--open > .select2-dropdown > .select2-results > ul', 'Investment account EUR');
        });
    }

    public function test_currency_displayed_correctly_for_various_settings_1()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                // Open transaction investment form
                ->visitRoute('transaction.create', ['type' => 'investment'])
                ->assertPresent('#transactionFormInvestment')

                // Select account
                ->select2ExactSearch('#account', 'Investment account USD', 10)

                // Validate currency is displayed correctly
                ->waitForTextIn('@label-currency', '$')

                // Remove the account
                ->select2ClearAll('#account', '#transactionFormInvestment')

                // Validate that no currency is displayed
                ->assertNotPresent('@label-currency')

                // Select investment
                ->select2ExactSearch('#investment', 'Test investment EUR', 10)

                // Validate currency is displayed correctly
                ->waitForTextIn('@label-currency', '€')

                // Remove the investment
                ->select2ClearAll('#investment', '#transactionFormInvestment')

                // Validate that no currency is displayed
                ->assertNotPresent('@label-currency');
        });
    }

    public function test_currency_displayed_correctly_for_various_settings_2()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                // Open transaction investment form
                ->visitRoute('transaction.create', ['type' => 'investment'])
                ->assertPresent('#transactionFormInvestment')

                // Select investment
                ->select2ExactSearch('#investment', 'Test investment EUR', 10)

                // Validate currency is displayed correctly
                ->waitForTextIn('@label-currency', '€')

                // Remove the investment
                ->select2ClearAll('#investment', '#transactionFormInvestment')

                // Validate that no currency is displayed
                ->assertNotPresent('@label-currency')

                // Select account
                ->select2ExactSearch('#account', 'Investment account USD', 10)

                // Validate currency is displayed correctly
                ->waitForTextIn('@label-currency', '$')

                // Remove the account
                ->select2ClearAll('#account', '#transactionFormInvestment')

                // Validate that no currency is displayed
                ->assertNotPresent('@label-currency');
        });
    }

    public function test_user_can_submit_buy_transaction_form()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user);

            // Fill form
            $this->fillStandardBuyForm($browser)
                // Verify that dividend field is disabled
                ->assertDisabled('#transaction_dividend')
                // Submit form
                ->clickAndWaitForReload('#transactionFormInvestment-Save')
                // A success message should be available in a Vue component
                ->waitForTextIn('#BootstrapNotificationContainer', 'Transaction added', 10);
        });
    }

    public function test_user_can_submit_sell_transaction_form()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visitRoute('transaction.create', ['type' => 'investment'])
                // Select account
                ->select2ExactSearch('#account', 'Investment account USD', 10)
                // Select investment
                ->select2ExactSearch('#investment', 'Test investment USD', 10)
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
                ->clickAndWaitForReload('#transactionFormInvestment-Save')
                // A success message should be available in a Vue component
                ->waitForTextIn('#BootstrapNotificationContainer', 'Transaction added', 10);
        });
    }

    public function test_user_can_submit_dividend_transaction_form()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visitRoute('transaction.create', ['type' => 'investment'])
                // Select account
                ->select2ExactSearch('#account', 'Investment account USD', 10)
                // Select investment
                ->select2ExactSearch('#investment', 'Test investment USD', 10)
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
                ->clickAndWaitForReload('#transactionFormInvestment-Save', 10)
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
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visitRoute('transaction.create', ['type' => 'investment'])
                // Select account
                ->select2ExactSearch('#account', 'Investment account USD', 10)
                // Select investment
                ->select2ExactSearch('#investment', 'Test investment USD', 10)
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
                ->clickAndWaitForReload('#transactionFormInvestment-Save')
                // A success message should be available in a Vue component
                ->waitForTextIn('#BootstrapNotificationContainer', 'Transaction added', 10);
        });
    }

    public function test_user_can_submit_transaction_with_schedule()
    {
        $this->markTestIncomplete('Not implemented yet.');
    }

    public function test_callback_add_an_other_transaction()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user);
            $this->fillStandardBuyForm($browser)
                ->click('button[value="create"]')
                // Submit form
                ->clickAndWaitForReload('#transactionFormInvestment-Save')
                ->assertRouteIs('transaction.create', ['type' => 'investment']);
        });
    }

    public function test_callback_clone_transaction()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user);
            $this->fillStandardBuyForm($browser)
                ->click('button[value="clone"]')
                // Submit form
                ->clickAndWaitForReload('#transactionFormInvestment-Save');

            // Get the last transaction from the database
            $transaction = Transaction::orderBy('id', 'desc')->first();

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

    public function test_callback_show_transaction()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user);
            $this->fillStandardBuyForm($browser)
                ->click('button[value="show"]')
                // Submit form
                ->clickAndWaitForReload('#transactionFormInvestment-Save');

            // Get the last transaction from the database
            $transaction = Transaction::orderBy('id', 'desc')->first();

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

    public function test_callback_return_to_selected_account()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user);
            $this->fillStandardBuyForm($browser)
                ->click('button[value="returnToPrimaryAccount"]')
                // Submit form
                ->clickAndWaitForReload('#transactionFormInvestment-Save');

            // Get the last transaction from the database
            $transaction = Transaction::orderBy('id', 'desc')
                ->with(['config'])
                ->first();

            $browser->assertRouteIs(
                'account-entity.show',
                ['account_entity' => $transaction->config->account_id]
            );
        });
    }

    public function test_callback_return_to_selected_investment()
    {
        $this->markTestIncomplete('This function is not implemented yet');
    }

    public function test_callback_return_to_dashboard()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user);
            $this->fillStandardBuyForm($browser)
                ->click('button[value="returnToDashboard"]')
                // Submit form
                ->clickAndWaitForReload('#transactionFormInvestment-Save');

            $browser->assertRouteIs('home');
        });
    }

    public function test_callback_return_to_previous_page()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visitRoute('tag.index');
            $this->fillStandardBuyForm($browser)
                ->click('button[value="back"]')
                ->clickAndWaitForReload('#transactionFormInvestment-Save')
                ->assertRouteIs('tag.index');
        });
    }
}
