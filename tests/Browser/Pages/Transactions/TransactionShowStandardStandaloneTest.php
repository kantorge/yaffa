<?php

namespace Tests\Browser\Pages\Transactions;

use App\Models\Transaction;
use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class TransactionShowStandardStandaloneTest extends DuskTestCase
{
    protected static bool $migrationRun = false;

    protected function setUp(): void
    {
        parent::setUp();

        // Migrate and seed only once for this file
        if (!static::$migrationRun) {
            $this->artisan('migrate:fresh');
            $this->artisan('db:seed');
            static::$migrationRun = true;
        }
    }

    public function test_user_can_load_the_standard_transaction_details(): void
    {
        $user = User::firstWhere('email', $this::USER_EMAIL);

        // Create a standard transaction with specific data
        $transaction = Transaction::factory()
            ->for($user)
            ->withdrawal($user)
            ->create([
                'comment' => 'Test comment',
                'reconciled' => true,
            ]);

        $this->browse(function (Browser $browser) use ($user, $transaction) {
            $browser->loginAs($user)
                // Load the transaction page
                ->visitRoute('transaction.open', ['transaction' => $transaction->id, 'action' => 'show'])
                // Check the details container is present
                ->assertPresent('#transactionShowStandard')

                // TODO: Check the details are correct

                // Action button bar is present
                ->assertPresent('@action-bar')
                // Close and open button is not available in the action bar
                ->assertMissing('@button-action-bar-close')
                ->assertMissing('@button-action-bar-open')
                // Skip and enter instance buttons are not available in the action bar
                ->assertMissing('@button-action-bar-skip')
                ->assertMissing('@button-action-bar-enter-instance');
        });
    }
}
