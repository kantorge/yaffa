<?php

namespace Tests\Browser\Pages\Transactions;

use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class TransactionFormStandardTest extends DuskTestCase
{
    protected static $migrationRun = false;

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

    public function test_user_can_load_the_standard_transaction_form()
    {
        $user = User::firstWhere('email', 'demo@yaffa.cc');

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visitRoute('transactions.createStandard')
                ->assertPresent('#transactionFormStandard');
        });
    }

    public function test_user_cannot_submit_standard_transaction_form_with_errors()
    {
        $this->browse(function (Browser $browser) {
            $browser
                ->visitRoute('transactions.createStandard')
                // Try to save form without any data
                ->pressAndWaitFor('#transactionFormStandard-Save')
                // The page should no have changed
                ->assertRouteIs('transactions.createStandard')
                // Error messages should be displayed in Bootstrap alert
                ->assertPresent('#transactionFormStandard .alert.alert-danger');
        });
    }

    public function test_currency_displayed_correctly_for_various_settings()
    {
        $this->browse(function (Browser $browser) {
            $browser
                // Open vanilla form (withdrawal, no preselected account)
                ->visitRoute('transactions.createStandard')

                // No currency should be visible
                ->assertNotPresent('@label-amountFrom-currency');

            // Select account from, currency symbol should be visible

            // Remove account from, no currency symbol should be visible

            // Select account from again, and swithc to deposit
            // No currency should be visible

            // Select account to, currency symbol should be visible

            // Remove account to, no currency symbol should be visible

            // Select account to again, and switch to transfer
            // Account should remain, but no currency symbol should be visible

            // Select account from with the same currency
            // Currency of account from should be visible

            // Select account fromj with different currency
            // This new currency should be visible
            // Secondary amount and currency symbol should be visible
        });
    }
}
