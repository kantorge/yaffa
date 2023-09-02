<?php

namespace Tests\Browser\Pages\Transactions;

use App\Models\AccountEntity;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class TransactionFormStandardStandaloneTest extends DuskTestCase
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

    private function fillStandardWithdrawalForm(Browser $browser): Browser
    {
        return $browser
            // Open vanilla form (withdrawal, no preselected account)
            ->visitRoute('transaction.create', ['type' => 'standard'])
            // Wait for the form to load
            ->waitFor('#transactionFormStandard')
            // Select account from, random from dropdown
            ->select2('#account_from', null, 10)
            // Select payeee, random from dropdown
            ->select2('#account_to', null, 10)
            // Add amount
            ->type('#transaction_amount_from', '100')
            // Allocate the same amount to a random category by adding one new item
            ->click('@button-add-transaction-item')
            // Set the first category input
            ->select2('#transaction_item_container .transaction_item_row select.category', null, 10)
            // Set the first amount to the same amount as the transaction
            ->type('#transaction_item_container .transaction_item_row input.transaction_item_amount', '100');
    }

    public function test_user_can_load_the_standard_transaction_form()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
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
            $browser->loginAs($this->user)
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
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                // Open vanilla form (withdrawal, no preselected account)
                ->visitRoute('transaction.create', ['type' => 'standard'])

                // No currency should be visible
                ->assertNotPresent('@label-amountFrom-currency')

                // Select account from (EUR)
                ->select2ExactSearch('#account_from', 'Investment account EUR', 10)
                ->assertSeeIn('#account_from + .select2', 'Investment account EUR')

                // Currency symbol should be visible, when Vue has updated the value from AJAX call
                ->waitForTextIn('@label-amountFrom-currency', '€', 10)
                ->assertSeeIn('@label-amountFrom-currency', '€')

                // Remove account from, no currency symbol should be visible
                ->select2ClearAll('#account_from', '#transactionFormStandard')
                ->assertNotPresent('@label-amountFrom-currency')

                // Select account from again, and switch to deposit
                ->select2ExactSearch('#account_from', 'Investment account EUR', 10)
                ->click('@transaction-type-deposit')
                // Confirm alert
                ->acceptDialog()

                // No currency should be visible
                ->assertNotPresent('@label-amountFrom-currency')

                // Select account to, currency symbol should be visible
                ->select2ExactSearch('#account_to', 'Investment account EUR', 10)
                ->waitForTextIn('@label-amountFrom-currency', '€', 10)

                // Remove account to, no currency symbol should be visible
                ->select2ClearAll('#account_to', '#transactionFormStandard')
                ->waitUntilMissing('@label-amountFrom-currency', 10)

                // Select account to again, and switch to transfer
                ->select2ExactSearch('#account_to', 'Investment account EUR', 10)
                ->click('@transaction-type-transfer')
                // Confirm alert
                ->acceptDialog()

                // Account in select2 should remain, but no currency symbol should be visible
                ->assertSeeIn('#account_to + .select2', 'Investment account EUR')
                ->assertNotPresent('@label-amountFrom-currency')

                // Select a different account from with the same currency
                ->select2ExactSearch('#account_from', 'Cash account EUR', 10)

                // Currency of account from should be visible
                ->waitForTextIn('@label-amountFrom-currency', '€', 10)

                // Select account from with different currency
                ->select2ExactSearch('#account_from', 'Cash account USD', 10)

                // This new currency should be visible
                ->assertPresent('@label-amountFrom-currency')
                ->waitForTextIn('@label-amountFrom-currency', '$', 10)

                // Secondary amount and currency symbol should be visible
                ->assertPresent('@label-amountTo-currency')
                ->assertSeeIn('@label-amountTo-currency', '€');
        });
    }

    public function test_user_can_submit_withdrawal_transaction_form()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user);
            $this->fillStandardWithdrawalForm($browser)
                // Submit form
                ->clickAndWaitForReload('#transactionFormStandard-Save')
                // A success message should be available in a Vue component
                ->waitForTextIn('#BootstrapNotificationContainer', 'Transaction added', 10);
        });
    }

    public function test_user_can_submit_deposit_transaction_form()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                // Open vanilla form (withdrawal, no preselected account)
                ->visitRoute('transaction.create', ['type' => 'standard'])
                // Switch to deposit transaction type
                ->click('@transaction-type-deposit')
                // Confirm alert
                ->acceptDialog()
                // Select account to, random from dropdown
                ->select2('#account_to', null, 10)
                // Select payeee, random from dropdown
                ->select2('#account_from', null, 10)
                // Add amount
                ->type('#transaction_amount_from', '100')
                // Allocate the same amount to a random category by adding one new item
                ->click('@button-add-transaction-item')
                // Set the first category input
                ->select2('#transaction_item_container .transaction_item_row select.category', null, 10)
                // Set the first amount to the same amount as the transaction
                ->type('#transaction_item_container .transaction_item_row input.transaction_item_amount', '100')
                // Submit form
                ->clickAndWaitForReload('#transactionFormStandard-Save')
                // A success message should be available in a Vue component
                ->waitForTextIn('#BootstrapNotificationContainer', 'Transaction added', 10);
        });
    }

    public function test_user_can_submit_transfer_transaction_form_with_same_currencies()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                // Open vanilla form (withdrawal, no preselected account)
                ->visitRoute('transaction.create', ['type' => 'standard'])
                // Switch to deposit transaction type
                ->click('@transaction-type-transfer')
                // Confirm alert
                ->acceptDialog()
                // Select account from, with USD currency
                ->select2ExactSearch('#account_from', 'Cash account USD', 10)
                // Select account to, with USD currency
                ->select2ExactSearch('#account_to', 'Investment account USD', 10)
                // Add amount
                ->type('#transaction_amount_from', '100')
                // Submit form
                ->clickAndWaitForReload('#transactionFormStandard-Save')
                // A success message should be available in a Vue component
                ->waitForTextIn('#BootstrapNotificationContainer', 'Transaction added', 10);
        });
    }

    public function test_user_can_submit_transaction_form_with_different_currencies()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                // Open vanilla form (withdrawal, no preselected account)
                ->visitRoute('transaction.create', ['type' => 'standard'])
                // Switch to deposit transaction type
                ->click('@transaction-type-transfer')
                // Confirm alert
                ->acceptDialog()
                // Select account from, with USD currency
                ->select2ExactSearch('#account_from', 'Cash account USD', 10)
                // Select account to, with EUR currency
                ->select2ExactSearch('#account_to', 'Investment account EUR', 10)
                // Add amount from
                ->type('#transaction_amount_from', '100')
                // User cannot send the form, as amount to is missing
                ->press('#transactionFormStandard-Save')
                // Wait for the error message
                ->waitForTextIn('#transactionFormStandard .alert', 'The amount to field is required.', 10)
                // Add amount to
                ->type('#transaction_amount_to', '100')
                // Exchange rate should be displayed after clicking out of the amount to field
                ->click('#transactionFormStandard')
                ->waitForTextIn('@label-transaction-exchange-rate', '1.0000')
                // Submit form
                ->clickAndWaitForReload('#transactionFormStandard-Save')
                // A success message should be available in a Vue component
                ->waitForTextIn('#BootstrapNotificationContainer', 'Transaction added', 10);
        });
    }

    public function test_automatic_recording_is_enabled_only_for_scheduled_transactions()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user);
            $browser
                // Open vanilla form (withdrawal, no preselected account)
                ->visitRoute('transaction.create', ['type' => 'standard'])
                // Wait for the form to load
                ->waitFor('#transactionFormStandard')
                // The schedule card should not be visible
                ->assertMissing('@card-transaction-schedule')
                // Select budget checkbox
                ->click('@checkbox-transaction-budget')
                // The schedule card should be visible, but the automatic recording checkbox should not be visible
                ->assertVisible('@card-transaction-schedule')
                ->assertMissing('@checkbox-schedule-automatic-recording')
                // Select schedule checkbox
                ->click('@checkbox-transaction-schedule')
                // The automatic recording checkbox should be visible
                ->assertVisible('@checkbox-schedule-automatic-recording')
                // Unselect the budget checkbox
                ->click('@checkbox-transaction-budget')
                // The automatic recording checkbox should still be visible
                ->assertVisible('@checkbox-schedule-automatic-recording');
        });
    }

    public function test_user_can_submit_transaction_form_with_schedule()
    {
        $this->markTestIncomplete('This test has not been implemented yet.');
    }

    public function test_callback_add_an_other_transaction()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user);
            $this->fillStandardWithdrawalForm($browser)
                ->click('button[value="create"]')
                // Submit form
                ->clickAndWaitForReload('#transactionFormStandard-Save')
                ->assertRouteIs('transaction.create', ['type' => 'standard']);
        });
    }

    public function test_callback_clone_this_transaction()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user);
            $this->fillStandardWithdrawalForm($browser)
                ->click('button[value="clone"]')
                ->clickAndWaitForReload('#transactionFormStandard-Save');

            // Get the latest transaction from the database
            $transaction = Transaction::orderBy('id', 'desc')->first();

            // Check that the view is the transaction clone
            $browser->assertRouteIs(
                'transaction.open',
                [
                    'action' => 'clone',
                    'transaction' => $transaction->id,
                ]
            );
        });
    }

    public function test_callback_show_transaction()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user);
            $this->fillStandardWithdrawalForm($browser)
                ->click('button[value="show"]')
                ->clickAndWaitForReload('#transactionFormStandard-Save');

            // Get the latest transaction from the database
            $transaction = Transaction::orderBy('id', 'desc')->first();

            // Check that the view is the transaction show
            $browser->assertRouteIs(
                'transaction.open',
                [
                    'action' => 'show',
                    'transaction' => $transaction->id,
                ]
            );
        });
    }

    public function test_callback_return_to_selected_account()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user);
            $this->fillStandardWithdrawalForm($browser)
                ->click('button[value="returnToPrimaryAccount"]')
                ->clickAndWaitForReload('#transactionFormStandard-Save');

            // Get the latest transaction from the database
            $transaction = Transaction::orderBy('id', 'desc')
                ->with([
                    'config',
                ])
                ->first();

            $browser->assertRouteIs(
                'account-entity.show',
                ['account_entity' => $transaction->config->account_from_id]
            );
        });
    }

    public function test_callback_return_to_target_account_for_transfer()
    {
        // Select account from, with USD currency
        $account = AccountEntity::firstWhere('name', 'Cash account USD');

        $this->browse(function (Browser $browser) use ($account) {
            $browser->loginAs($this->user)
                // Open vanilla form (withdrawal, no preselected account)
                ->visitRoute('transaction.create', ['type' => 'standard'])
                // Switch to transfer
                ->click('@transaction-type-transfer')
                // Confirm alert
                ->acceptDialog()

                // Add minimum necessary fields
                ->select2ExactSearch('#account_to', $account->name, 10)
                ->select2ExactSearch('#account_from', 'Investment account EUR', 10)
                ->type('#transaction_amount_from', '100')
                ->type('#transaction_amount_to', '100')

                // Submit form
                ->click('button[value="returnToSecondaryAccount"]')
                ->clickAndWaitForReload('#transactionFormStandard-Save');

            $browser->assertRouteIs(
                'account-entity.show',
                ['account_entity' => $account->id]
            );
        });
    }

    public function test_callback_return_to_dashboard()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user);
            $this->fillStandardWithdrawalForm($browser)
                ->click('button[value="returnToDashboard"]')
                ->clickAndWaitForReload('#transactionFormStandard-Save')
                ->assertRouteIs('home');
        });
    }

    public function test_callback_return_to_previous_page()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visitRoute('tag.index');
            $this->fillStandardWithdrawalForm($browser)
                ->click('button[value="back"]')
                ->clickAndWaitForReload('#transactionFormStandard-Save')
                ->assertRouteIs('tag.index');
        });
    }

    public function test_user_can_add_multiple_transaction_items()
    {
        $this->markTestIncomplete('This test has not been implemented yet.');

        // Check that item total must match the transaction total
    }

    public function test_user_can_add_and_use_a_new_payee()
    {
        // Create a new category, and ensure that it is a child category and active
        $category = Category::factory()->create([
            'user_id' => $this->user->id,
            'parent_id' => Category::inRandomOrder()->parentCategory()->first()->id,
            'active' => true,
        ]);

        $this->browse(function (Browser $browser) use ($category) {
            $browser->loginAs($this->user)
                // Open vanilla form (withdrawal, no preselected account)
                ->visitRoute('transaction.create', ['type' => 'standard'])
                // Select account from, random from dropdown
                ->select2('#account_from', null, 10)

                // Open the payee modal
                ->click('#account_to_container > button')

                // Verify that the modal is open
                ->assertVisible('#newPayeeModal')

                // Fill in the payee name
                ->type('#newPayeeModal #name', 'New Payee From Modal')

                // Select random default category
                ->select2ExactSearch('#newPayeeModal #category_id', $category->full_name, 10)

                // Submit the payee form
                ->click('#newPayeeModal button[type="submit"]')

                // Verify that the modal is closed
                ->waitUntilMissing('#newPayeeModal')

                // Add amount
                ->type('#transaction_amount_from', '100')

                // Add a new transaction item
                ->click('@button-add-transaction-item')

                // Set the first category input to the random category
                ->select2('#transaction_item_container .transaction_item_row select.category', $category->name, 10)
                //->select2ExactSearch('#transaction_item_container .transaction_item_row select.category', $category->fullName, 10)

                // Set the first amount to the same amount as the transaction
                ->type('#transaction_item_container .transaction_item_row input.transaction_item_amount', '100')

                // Set the callback to show the transaction
                ->click('button[value="show"]')

                // Submit the form
                ->clickAndWaitForReload('#transactionFormStandard-Save');

            // Get the latest transaction from the database
            $transaction = Transaction::orderBy('id', 'desc')
                ->with([
                    'config',
                    'config.accountTo'
                ])
                ->first();

            // Verify that the transaction has the new payee
            $browser->assertSeeIn('@label-account-to-name', $transaction->config->accountTo->name);
        });
    }

    public function test_user_can_reactivate_a_payee_through_the_new_payee_modal()
    {
        // Create an inactive payee
        $payee = AccountEntity::factory()
            ->payee($this->user)
            ->create([
                'user_id' => $this->user->id,
                'active' => false,
            ]);

        $this->browse(function (Browser $browser) use ($payee) {
            $browser->loginAs($this->user)
                // Open vanilla form (withdrawal, no preselected account)
                ->visitRoute('transaction.create', ['type' => 'standard'])

                // Open the payee modal
                ->click('#account_to_container > button')

                // Fill in the payee name
                ->type('#newPayeeModal #name', $payee->name)

                // Check if the payee was found as existing and is inactive
                ->waitForTextIn('#newPayeeModal #similar-payee-list li[data-id="' . $payee->id . '"]', $payee->name)
                ->assertSeeIn('#newPayeeModal #similar-payee-list li[data-id="' . $payee->id . '"]', '(inactive)')

                // Select the payee from the list
                ->click('#newPayeeModal #similar-payee-list li[data-id="' . $payee->id . '"] a')

                // There is a 1 second delay between clicking the payee and the modal closing
                ->pause(1000)

                // Verify that the modal is closed
                ->waitUntilMissing('#newPayeeModal.show', 10)

                // Verify that the payee is added to the transaction
                ->assertSeeIn('#account_to + .select2', $payee->name);
        });

        // Verify that the payee is active
        $this->assertTrue($payee->fresh()->active);
    }

    public function test_add_new_payee_button_visibility_adopts_to_transaction_type()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                // Open vanilla form (withdrawal, no preselected account)
                ->visitRoute('transaction.create', ['type' => 'standard'])
                // Verify that the add new payee button is visible next to the account to dropdown
                ->assertVisible('#account_to_container > button[data-coreui-target="#newPayeeModal"]')

                // Switch to deposit and confirm dialog
                ->click('@transaction-type-deposit')
                ->acceptDialog()
                // Verify that the add new payee button is not visible next to the account from dropdown
                ->assertVisible('#account_from_container > button[data-coreui-target="#newPayeeModal"]')

                // Switch to transfer and confirm dialog
                ->click('@transaction-type-transfer')
                ->acceptDialog()
                // Verify that the add new payee button is not visible
                ->assertMissing('#account_to_container > button[data-coreui-target="#newPayeeModal"]')
                ->assertMissing('#account_from_container > button[data-coreui-target="#newPayeeModal"]');
        });
    }
}
