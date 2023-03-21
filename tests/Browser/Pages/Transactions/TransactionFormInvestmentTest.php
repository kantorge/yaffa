<?php

namespace Tests\Browser\Pages\Transactions;

use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class TransactionFormInvestmentTest extends DuskTestCase
{
    protected static bool $migrationRun = false;

    public function setUp(): void
    {
        parent::setUp();

        // Migrate and seed only once for this file
        if (!static::$migrationRun) {
            $this->artisan('migrate:fresh');
            $this->artisan('db:seed');
            static::$migrationRun = true;
        }
    }

    public function test_user_can_load_the_investment_transaction_form()
    {
        $user = User::firstWhere('email', 'demo@yaffa.cc');

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visitRoute('transactions.createInvestment')
                ->assertPresent('#transactionFormInvestment');
        });
    }

    public function test_user_cannot_submit_investment_transaction_form_with_errors()
    {
        $this->browse(function (Browser $browser) {
            $browser
                ->visitRoute('transactions.createInvestment')
                // Try to save form without any data
                ->pressAndWaitFor('#transactionFormInvestment-Save')
                // The page should no have changed
                ->assertRouteIs('transactions.createInvestment')
                // Error messages should be displayed in Bootstrap alert
                ->assertPresent('#transactionFormInvestment .alert.alert-danger');
        });
    }

    public function test_selecting_an_account_limits_investments_to_the_same_currency()
    {
        // Select2 seems to return a random item even with the helper package
        // https://calebporzio.com/a-simple-trick-to-auto-retry-failing-dusk-tests
        retry(5, function () {
            $this->browse(function (Browser $browser) {
                $browser
                    ->visitRoute('transactions.createInvestment')
                    // Select account
                    ->select2('#account', 'Investment account USD')
                    ->assertSeeIn('#account + .select2', 'Investment account USD')
                    // Try to select an investment
                    ->click('#investment + .select2')
                    ->waitFor('.select2-container--open')
                    ->assertSeeIn('.select2-container--open > .select2-dropdown > .select2-results > ul', 'Test investment USD')
                    ->assertDontSeeIn('.select2-container--open > .select2-dropdown > .select2-results > ul', 'Test investment EUR');
            });
        });
    }

    public function test_selecting_an_investment_limits_accounts_to_the_same_currency()
    {
        // Select2 seems to return a random item even with the helper package
        // https://calebporzio.com/a-simple-trick-to-auto-retry-failing-dusk-tests
        retry(5, function () {
            $this->browse(function (Browser $browser) {
                $browser
                    ->visitRoute('transactions.createInvestment')
                    // Select investment
                    ->select2('#investment', 'Test investment USD')
                    ->assertSeeIn('#investment + .select2', 'Test investment USD')
                    // Try to select an account
                    ->click('#account + .select2')
                    ->waitFor('.select2-container--open')
                    ->assertSeeIn('.select2-container--open > .select2-dropdown > .select2-results > ul', 'Investment account USD')
                    ->assertDontSeeIn('.select2-container--open > .select2-dropdown > .select2-results > ul', 'Investment account EUR');
            });
        });
    }
}
