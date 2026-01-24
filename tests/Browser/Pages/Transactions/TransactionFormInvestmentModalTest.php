<?php

namespace Tests\Browser\Pages\Transactions;

use App\Models\AccountEntity;
use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class TransactionFormInvestmentModalTest extends DuskTestCase
{
    protected static bool $migrationRun = false;

    protected User $user;
    protected AccountEntity $accountEntity;

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
        $this->accountEntity = AccountEntity::firstWhere('name', 'Investment account USD');
    }

    public function test_user_can_load_the_investment_transaction_form_in_a_modal(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                // Load the view for a random account
                ->visitRoute('account-entity.show', ['account_entity' => $this->accountEntity->id])
                // Wait for the page to load
                ->waitForText('Account details')
                // Click the "new investment transaction" button
                ->click('#create-investment-transaction-button')
                // Wait for the modal to load
                ->waitForText('Add new transaction')
                // The modal should be visible
                ->assertVisible('#modal-transaction-form-investment')
                // The form should be visible
                ->assertVisible('#transactionFormInvestment')
                // The save button should be visible
                ->assertVisible('#transactionFormInvestment-Save')
                // The "after save" button group should not be present
                ->assertNotPresent('@action-after-save-desktop-button-group');
        });
    }

    public function test_user_can_interact_with_reconciled_date_and_comment_fields_in_modal(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                // Load the view for a random account
                ->visitRoute('account-entity.show', ['account_entity' => $this->accountEntity->id])
                // Wait for the page to load
                ->waitForText('Account details')
                // Click the "new investment transaction" button
                ->click('#create-investment-transaction-button')
                // Wait for the modal to load
                ->waitForText('Add new transaction')
                ->waitFor('#transactionFormInvestment')

                // Test the reconciled checkbox with prefixed ID
                ->assertPresent('#checkbox-investment-transaction-reconciled')
                ->click('label[for="checkbox-investment-transaction-reconciled"]')
                ->assertChecked('#checkbox-investment-transaction-reconciled')
                ->click('label[for="checkbox-investment-transaction-reconciled"]')
                ->assertNotChecked('#checkbox-investment-transaction-reconciled')

                // Test the date field with prefixed ID
                ->assertPresent('#investment-date')
                ->type('#investment-date', '2025-01-15')
                ->assertInputValue('#investment-date', '2025-01-15')

                // Test the comment field with prefixed ID
                ->assertPresent('#investment-comment')
                ->type('#investment-comment', 'Test comment from modal')
                ->assertInputValue('#investment-comment', 'Test comment from modal');
        });
    }

    public function test_user_can_submit_buy_transaction_form_in_modal(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                // Load the view for a random account
                ->visitRoute('account-entity.show', ['account_entity' => $this->accountEntity->id])
                // Wait for the page to load
                ->waitForText('Account details')
                // Click the "new investment transaction" button
                ->click('#create-investment-transaction-button')
                // Wait for the modal to load
                ->waitForText('Add new transaction')
                ->waitFor('#transactionFormInvestment')
                ->waitFor('#account', 10)
                ->waitFor('#investment', 10)

                // Fill the form
                // Account is pre-selected (account_entity 1)
                // Select investment, random from dropdown
                ->select2('#investment', null, 10);

            // Get the selected investment text to verify it later
            $selectedInvestment = $browser->text('#investment + .select2 .select2-selection__rendered');

            $browser
                // Select transaction type
                ->select('#transaction_type', 'Buy')
                // Add quantity
                ->type('#transaction_quantity', '10')
                // Add price
                ->type('#transaction_price', '20')
                // Add commission
                ->type('#transaction_commission', '30')
                // Add taxes
                ->type('#transaction_tax', '40')

                // Submit form
                ->click('#transactionFormInvestment-Save')
                // Wait for the modal to close
                ->waitUntilMissing('#modal-transaction-form-investment', 10)
                // A success message should be available
                ->waitForTextIn('.toast-container .toast.bg-success.show', 'Transaction added', 10);

            // Verify the transaction was saved in the database
            $transaction = \App\Models\Transaction::orderByDesc('id')->first();
            $this->assertNotNull($transaction);
            $this->assertEquals(10, $transaction->config->quantity);
            $this->assertEquals(20, $transaction->config->price);

            // Now reopen the modal and verify investment is cleared
            $browser
                // Click the "new investment transaction" button again
                ->click('#create-investment-transaction-button')
                // Wait for the modal to load
                ->waitForText('Add new transaction')
                ->waitFor('#transactionFormInvestment')
                ->waitFor('#account', 10)
                ->waitFor('#investment', 10)

                // Verify that the investment dropdown is cleared (shows placeholder)
                ->assertDontSeeIn('#investment + .select2 .select2-selection__rendered', $selectedInvestment)
                // The account should still be pre-selected
                ->assertSeeIn('#account + .select2 .select2-selection__rendered', $this->accountEntity->name);
        });
    }

    public function test_investment_is_cleared_after_cancel(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                // Load the view for a random account
                ->visitRoute('account-entity.show', ['account_entity' => $this->accountEntity->id])
                // Wait for the page to load
                ->waitForText('Account details')
                // Click the "new investment transaction" button
                ->click('#create-investment-transaction-button')
                // Wait for the modal to load
                ->waitForText('Add new transaction')
                ->waitFor('#transactionFormInvestment')
                ->waitFor('#account', 10)
                ->waitFor('#investment', 10)

                // Select an investment
                ->select2('#investment', null, 10);

            // Get the selected investment text
            $selectedInvestment = $browser->text('#investment + .select2 .select2-selection__rendered');

            $browser
                // Cancel the dialog by clicking the close button
                ->click('#modal-transaction-form-investment .modal-header .btn-close')
                // Wait for the modal to close
                ->waitUntilMissing('#modal-transaction-form-investment', 10)

                // Reopen the modal
                ->click('#create-investment-transaction-button')
                // Wait for the modal to load
                ->waitForText('Add new transaction')
                ->waitFor('#transactionFormInvestment')
                ->waitFor('#account', 10)
                ->waitFor('#investment', 10)

                // Verify that the investment dropdown is cleared (shows placeholder)
                ->assertDontSeeIn('#investment + .select2 .select2-selection__rendered', $selectedInvestment)
                // The account should still be pre-selected
                ->assertSeeIn('#account + .select2 .select2-selection__rendered', $this->accountEntity->name);
        });
    }
}
