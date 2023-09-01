<?php

namespace Tests\Browser\Pages\Transactions;

use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class TransactionFormInvestmentModalTest extends DuskTestCase
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

    public function test_user_can_load_the_investment_transaction_form_in_a_modal()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                // Load the view for a random account
                ->visitRoute('account-entity.show', ['account_entity' => 1])
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
                // The "after save" button group should not be visible
                ->assertPresent('@action-after-save-desktop-button-group')
                ->assertAttribute('@action-after-save-desktop-button-group', 'style', 'display: none;');
        });
    }
}
