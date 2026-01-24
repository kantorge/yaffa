<?php

namespace Tests\Browser\Pages\Transactions;

use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class TransactionFormStandardModalTest extends DuskTestCase
{
    protected static bool $migrationRun = false;

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

    public function test_user_can_load_the_standard_transaction_form_in_a_modal(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                // Load the view for a random account
                ->visitRoute('account-entity.show', ['account_entity' => 1])
                // Wait for the page to load
                ->waitForText('Account details')
                // Click the "new transaction" button
                ->click('#create-standard-transaction-button')
                // Wait for the modal to load
                ->waitForText('Add new transaction')
                // The modal should be visible
                ->assertVisible('#modal-transaction-form-standard')
                // The form should be visible
                ->assertVisible('#transactionFormStandard')
                // The save button should be visible
                ->assertVisible('#transactionFormStandard-Save')
                // The "after save" button group should not be present
                ->assertNotPresent('@action-after-save-desktop-button-group');
        });
    }

    public function test_add_new_payee_button_is_never_visible_in_the_modal(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                // Load the view for a random account
                ->visitRoute('account-entity.show', ['account_entity' => 1])
                // Wait for the page to load
                ->waitForText('Account details')
                // Click the "new transaction" button
                ->click('#create-standard-transaction-button')
                // Wait for the modal to load
                ->waitForText('Add new transaction')

                // Verify that the add new payee button is visible next to the account to dropdown
                ->assertNotPresent('#account_to_container > button[data-coreui-target="#newPayeeModal"]')

                // Switch to deposit and confirm dialog
                ->click('@transaction-type-deposit')
                ->acceptDialog()
                // Verify that the add new payee button is not visible next to the account from dropdown
                ->assertNotPresent('#account_from_container > button[data-coreui-target="#newPayeeModal"]')

                // Switch to transfer and confirm dialog
                ->click('@transaction-type-transfer')
                ->acceptDialog()
                // Verify that the add new payee button is not visible
                ->assertNotPresent('#account_to_container > button[data-coreui-target="#newPayeeModal"]')
                ->assertNotPresent('#account_from_container > button[data-coreui-target="#newPayeeModal"]');
        });
    }

    public function test_user_can_interact_with_reconciled_date_and_comment_fields_in_modal(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                // Load the view for a random account
                ->visitRoute('account-entity.show', ['account_entity' => 1])
                // Wait for the page to load
                ->waitForText('Account details')
                // Click the "new transaction" button
                ->click('#create-standard-transaction-button')
                // Wait for the modal to load
                ->waitForText('Add new transaction')
                ->waitFor('#transactionFormStandard')

                // Test the reconciled checkbox with prefixed ID
                ->assertPresent('#checkbox-standard-transaction-reconciled')
                ->click('label[for="checkbox-standard-transaction-reconciled"]')
                ->assertChecked('#checkbox-standard-transaction-reconciled')
                ->click('label[for="checkbox-standard-transaction-reconciled"]')
                ->assertNotChecked('#checkbox-standard-transaction-reconciled')

                // Test the date field with prefixed ID
                ->assertPresent('#standard-date')
                ->type('#standard-date', '2025-01-15')
                ->assertInputValue('#standard-date', '2025-01-15')

                // Test the comment field with prefixed ID
                ->assertPresent('#standard-comment')
                ->type('#standard-comment', 'Test comment from modal')
                ->assertInputValue('#standard-comment', 'Test comment from modal');
        });
    }

    public function test_user_can_submit_withdrawal_transaction_form_in_modal(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                // Load the view for a random account
                ->visitRoute('account-entity.show', ['account_entity' => 1])
                // Wait for the page to load
                ->waitForText('Account details')
                // Click the "new transaction" button
                ->click('#create-standard-transaction-button')
                // Wait for the modal to load
                ->waitForText('Add new transaction')
                ->waitFor('#transactionFormStandard')

                // Fill the form
                // Account from is pre-selected (account_entity 1)
                // Select payee (account to), random from dropdown
                ->select2('#account_to', null, 10)
                // Add amount
                ->type('#transaction_amount_from', '100')
                // Allocate the same amount to a random category by adding one new item
                ->click('@button-add-transaction-item')
                // Set the first category input
                ->select2('#transaction_item_container .transaction_item_row select.category', null, 10)
                // Set the first amount to the same amount as the transaction
                ->type('#transaction_item_container .transaction_item_row input.transaction_item_amount', '100')

                // Submit form
                ->click('#transactionFormStandard-Save')
                // Wait for the modal to close
                ->waitUntilMissing('#modal-transaction-form-standard', 10)
                // A success message should be available
                ->waitForTextIn('.toast-container .toast.bg-success.show', 'Transaction added', 10);

            // Verify the transaction was saved in the database
            $transaction = \App\Models\Transaction::orderByDesc('id')->first();
            $this->assertNotNull($transaction);
            $this->assertEquals(100, $transaction->config->amount_from);
        });
    }
}
