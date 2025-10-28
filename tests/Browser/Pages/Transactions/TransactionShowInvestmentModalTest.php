<?php

use App\Models\AccountEntity;
use App\Models\Investment;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
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
});


test('user can view the investment transaction details', function () {
    $user = User::firstWhere('email', $this::USER_EMAIL);

    // Create an investment transaction with specific data
    $transaction = Transaction::factory()
        ->for($user)
        ->buy(
            $user,
            [
                'account_id' => AccountEntity::where('name', 'Investment account USD')->first()->id,
                'investment_id' => Investment::where('name', 'Test investment USD')->first()->id,
                'price' => 1.23456,
                'quantity' => 2.34567,
                'commission' => 3.45678,
                'tax' => 4.56789,
            ]
        )
        ->create([
            'comment' => 'Test comment',
            'reconciled' => true,
            // Specific date that is not expected to be present
            'date' => Carbon::create(2000, 1, 1),
        ]);

    $this->browse(function (Browser $browser) use ($user, $transaction) {
        $browser->loginAs($user)
            // Load the 'find transactions' page
            ->visitRoute('reports.transactions', [
                'date_from' => '2000-01-01',
                'date_to' => '2000-01-01',
            ])

            // Wait for the results container to be present (targeting the navigation elements)
            ->waitFor('#nav-transaction-list')
            ->click('#nav-transaction-list')

            // Click the quick-view button for the transaction
            ->waitFor('#tab-transaction-list table button.transaction-quickview[data-id="' . $transaction->id . '"]')
            ->click('#tab-transaction-list table button.transaction-quickview[data-id="' . $transaction->id . '"]')

            // Check the modal is present
            ->waitFor('#modal-quickview')
            // Validate the ID in the header
            ->assertSeeIn('#modal-quickview .modal-title', '#' . $transaction->id);
    });
});
