<?php

namespace Tests\Browser\Pages\Transactions;

use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class TransactionFormStandardModalTest extends DuskTestCase
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

    public function test_user_can_load_the_standard_transaction_form()
    {
        $user = User::firstWhere('email', 'demo@yaffa.cc');

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
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
                // The "after save" button group should not be visible
                ->assertPresent('@action-after-save-desktop-button-group')
                ->assertAttribute('@action-after-save-desktop-button-group', 'style', 'display: none;');
        });
    }
}
