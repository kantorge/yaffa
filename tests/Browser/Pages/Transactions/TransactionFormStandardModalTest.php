<?php

use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

uses(Tests\DuskTestCase::class);
beforeEach(function () {
    // Migrate and seed only once for this file
    if (!static::$migrationRun) {
        $this->artisan('migrate:fresh');
        $this->artisan('db:seed');
        static::$migrationRun = true;
    }

    $this->user = User::firstWhere('email', $this::USER_EMAIL);
});


test('user can load the standard transaction form in a modal', function () {
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
            // The "after save" button group should not be visible
            ->assertPresent('@action-after-save-desktop-button-group')
            ->assertAttribute('@action-after-save-desktop-button-group', 'style', 'display: none;');
    });
});

test('add new payee button is never visible in the modal', function () {
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
});
