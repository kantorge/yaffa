<?php

namespace Tests\Browser\Pages\Transactions;

use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class TransactionFormStandardStandaloneTest extends DuskTestCase
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

    public function test_user_can_load_the_standard_transaction_form_in_a_modal()
    {
        $user = User::firstWhere('email', 'demo@yaffa.cc');

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visitRoute('transaction.create', ['type' => 'standard'])
                ->assertPresent('#transactionFormStandard')

                // Standalone view should have the after save action button group visible
                ->assertVisible('@action-after-save-desktop-button-group')

                // Save button is always visible
                ->assertVisible('#transactionFormStandard-Save');

            $browser->with('@action-after-save-desktop-button-group', function ($buttonGroup) {
                // After save option "return to selected account" should be always visible
                $buttonGroup->assertPresent('button[value="returnToPrimaryAccount"]')

                    // After save option "return to target account" should not be visible for default withdrawal
                    ->assertNotPresent('button[value="returnToSecondaryAccount"]');
            });

            // Switch transaction type to transfer to verify the "return to target account" button
            $browser->click('@transaction-type-transfer')
                // Confirm alert
                ->acceptDialog()
                ->with('@action-after-save-desktop-button-group', function ($buttonGroup) {
                    // After save option "return to selected account" should be always visible
                    $buttonGroup->assertPresent('button[value="returnToPrimaryAccount"]')

                        // After save option "return to target account" should be visible for transfer
                        ->assertPresent('button[value="returnToSecondaryAccount"]');
                });
        });
    }

    public function test_user_cannot_submit_standard_transaction_form_with_errors()
    {
        $this->browse(function (Browser $browser) {
            $browser
                ->visitRoute('transaction.create', ['type' => 'standard'])
                // Try to save form without any data
                ->pressAndWaitFor('#transactionFormStandard-Save')
                // The page should no have changed
                ->assertRouteIs('transaction.create', ['type' => 'standard'])
                // Error messages should be displayed in Bootstrap alert
                ->assertPresent('#transactionFormStandard .alert.alert-danger');
        });
    }

    public function test_currency_displayed_correctly_for_various_settings()
    {
        $this->markTestIncomplete('Select2 seems to return a random item even with the helper package');

        $this->browse(function (Browser $browser) {
            $browser
                // Open vanilla form (withdrawal, no preselected account)
                ->visitRoute('transaction.create', ['type' => 'standard'])

                // No currency should be visible
                ->assertNotPresent('@label-amountFrom-currency')

                // Select account from (EUR)
                ->select2('#account_from', 'Investment account EUR', 10)
                ->assertSeeIn('#account_from + .select2', 'Investment account EUR')

                // Currency symbol should be visible, when Vue has updated the value from AJAX call
                ->waitUntilVue('from.account_currency', '€', '@transaction-form-standard')
                ->assertPresent('@label-amountFrom-currency')
                ->assertSeeIn('@label-amountFrom-currency', '€')

                // Remove account from, no currency symbol should be visible
                ->select2('#account_from', '')
                ->assertNotPresent('@label-amountFrom-currency')

                // Select account from again, and switch to deposit
                ->select2('#account_from', 'Investment account EUR')
                ->click('@transaction-type-deposit')
                // Confirm alert
                ->acceptDialog()

                // No currency should be visible
                ->assertNotPresent('@label-amountFrom-currency')

                // Select account to, currency symbol should be visible
                ->select2('#account_to', 'Investment account EUR')

                // Remove account to, no currency symbol should be visible
                ->select2('#account_to', '')
                ->assertNotPresent('@label-amountFrom-currency')

                // Select account to again, and switch to transfer
                ->select2('#account_to', 'Investment account EUR')
                ->click('@transaction-type-transfer')
                // Confirm alert
                ->acceptDialog()

                // Account in select2 should remain, but no currency symbol should be visible
                ->assertSeeIn('#account_to + .select2', 'Investment account EUR')
                ->assertNotPresent('@label-amountFrom-currency')

                // Select a different account from with the same currency
                ->select2('#accountFrom', 'Cash account EUR')

                // Currency of account from should be visible
                ->assertPresent('@label-amountFrom-currency')
                ->assertSeeIn('@label-amountFrom-currency', '€')

                // Select account from with different currency
                ->select2('#account_from', 'Cash account USD')

                // This new currency should be visible
                ->assertPresent('@label-amountFrom-currency')
                ->assertSeeIn('@label-amountFrom-currency', '$')

                // Secondary amount and currency symbol should be visible
                ->assertPresent('@label-amountTo-currency')
                ->assertSeeIn('@label-amountTo-currency', '€');
        });
    }
}
