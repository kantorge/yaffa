<?php

namespace Tests\Browser\Pages\Transactions;

use App\Models\AccountEntity;
use App\Models\Investment;
use App\Models\Transaction;
use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class TransactionShowInvestmentStandaloneTest extends DuskTestCase
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

    public function test_user_can_load_the_investment_transaction_details()
    {
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
            ]);

        $this->browse(function (Browser $browser) use ($user, $transaction) {
            $browser->loginAs($user)
                // Load the transaction page
                ->visitRoute('transaction.open', ['transaction' => $transaction->id, 'action' => 'show'])
                // Check the details container is present
                ->assertPresent('#transactionShowInvestment')
                // Check the details are correct
                // Transaction type is 'Buy'
                ->assertSeeIn('@label-transaction-type', 'Buy')
                // Investment is 'Test investment USD'
                ->assertSeeIn('@label-investment-name', 'Test investment USD')
                // Account is 'Investment account USD'
                ->assertSeeIn('@label-account-name', 'Investment account USD')
                // Quantity is rounded to 4 decimal places
                ->assertSeeIn('@label-quantity', '2.3457')
                // Price is rounded to  decimal places
                // and has the currency symbol according to the account currency and user locale
                ->assertSeeIn('@label-price', '$1.23')
                // Dividend is not present, labelled as 'Not set'
                ->assertSeeIn('@label-dividend', 'Not set')
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

    /**
     * Test that a transaction with 'Dividend' type can be loaded
     */
    public function test_user_can_load_the_dividend_transaction_details()
    {
        $user = User::firstWhere('email', $this::USER_EMAIL);

        // Create a dividend transaction with specific data
        $transaction = Transaction::factory()
            ->for($user)
            ->dividend(
                $user,
                [
                    'account_id' => AccountEntity::where('name', 'Investment account USD')->first()->id,
                    'investment_id' => Investment::where('name', 'Test investment USD')->first()->id,
                    'quantity' => null,
                    'price' => null,
                    'dividend' => 100,
                    'tax' => 20,
                    'commission' => 10,
                ]
            )
            ->create();

        $this->browse(function (Browser $browser) use ($user, $transaction) {
            $browser->loginAs($user)
                // Load the transaction page
                ->visitRoute('transaction.open', ['transaction' => $transaction->id, 'action' => 'show'])
                // Check the details container is present
                ->assertPresent('#transactionShowInvestment')
                // Check the details are correct
                // Transaction type is 'Dividend'
                ->assertSeeIn('@label-transaction-type', 'Dividend')
                // Investment is 'Test investment USD'
                ->assertSeeIn('@label-investment-name', 'Test investment USD')
                // Account is 'Investment account USD'
                ->assertSeeIn('@label-account-name', 'Investment account USD')
                // Quantity is rounded to 4 decimal places
                ->assertSeeIn('@label-quantity', 'Not set')
                // Dividend is rounded to 2 decimal places
                // and has the currency symbol according to the account currency and user locale
                ->assertSeeIn('@label-dividend', '100')
                // Price is not present, labelled as 'Not set'
                ->assertSeeIn('@label-price', 'Not set')
                // Action button bar is present
                ->assertPresent('@action-bar');
        });
    }

    /**
     * Test that a scheduled transaction can be loaded
     * and it has buttons for skipping and entering an instance
     **/
    public function test_user_can_load_the_investment_transaction_details_for_a_scheduled_transaction()
    {
        $user = User::firstWhere('email', $this::USER_EMAIL);

        // Create an investment transaction with specific data and a schedule
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
                'date' => null,
                'comment' => 'Test comment',
                'reconciled' => false,
                'schedule' => true,
            ]);

        $this->browse(function (Browser $browser) use ($user, $transaction) {
            $browser->loginAs($user)
                // Load the transaction page
                ->visitRoute('transaction.open', ['transaction' => $transaction->id, 'action' => 'show'])
                // Check the details container is present
                ->assertPresent('#transactionShowInvestment')

                // Action button bar is present
                ->assertPresent('@action-bar')

                // Skip and enter instance buttons are available in the action bar
                ->assertPresent('@button-action-bar-skip')
                ->assertPresent('@button-action-bar-enter-instance');
        });
    }
}
