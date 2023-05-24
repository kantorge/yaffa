<?php

namespace Tests\Browser\Pages\Partials;

use App\Models\AccountEntity;
use App\Models\Investment;
use App\Models\Transaction;
use App\Models\TransactionSchedule;
use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class ScheduledInvestmentTransactionsInDataTablesTest extends DuskTestCase
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

    public function test_details_of_a_buy_transaction_are_correct()
    {
        // Select main test user
        $user = User::firstWhere('email', 'demo@yaffa.cc');

        // Create an investment transaction with specific data
        $transaction = Transaction::factory()
            ->buy([
                'account_id' => AccountEntity::where('name', 'Investment account USD')->first()->id,
                'investment_id' => Investment::where('name', 'Test investment USD')->first()->id,
                'price' => 100,
                'quantity' => 2000,
                'commission' => 300,
                'tax' => 200,
                'dividend' => null,
            ])
            ->create([
                'user_id' => $user->id,
                'comment' => 'Test comment',
                'reconciled' => false,
                'schedule' => true,
                'budget' => false,
            ]);

        // Create the schedule for the transaction
        TransactionSchedule::factory()
            ->create([
                'transaction_id' => $transaction->id,
            ]);

        // Run the test
        $this->browse(function (Browser $browser) use ($user, $transaction) {
            $browser->loginAs($user)
                // Load the list of scheduled transactions
                ->visitRoute('report.schedules')
                // Wait for the table to load, when the placeholder is gone
                ->waitUntilMissing('#table .dataTables_empty', 10)
                // Check that a row with the transaction is present
                ->assertPresent('#table tbody tr[data-id="' . $transaction->id . '"]')
                // The 8th column is the payee, which contains the account name
                ->assertSeeIn('#table tbody tr[data-id="' . $transaction->id . '"] td:nth-child(8)', 'Investment account USD')
                // The 9th column is the category, which contains the investment type
                ->assertSeeIn('#table tbody tr[data-id="' . $transaction->id . '"] td:nth-child(9)', 'Buy');

            // Calculate the formatted value of the transaction using JavaScript
            $value = ($transaction->config->quantity ?? 0) * ($transaction->config->price ?? 0)
                + ($transaction->config->dividend ?? 0)
                + ($transaction->config->commission ?? 0) + ($transaction->config->tax ?? 0);
            $formattedValue = "- " . $browser
                ->script("const value = {$value};
                    return value.toLocaleString(
                    '{$user->locale}',
                    {
                        style: 'currency',
                        currency: '{$transaction->config->account->config->currency->iso_code}',
                        currencyDisplay: 'narrowSymbol',
                        minimumFractionDigits: {$transaction->config->account->config->currency->num_digits},
                        maximumFractionDigits: {$transaction->config->account->config->currency->num_digits},
                    });")[0];

            // The 10th column is the amount, which contains the formatted value
            $browser->assertSeeIn('#table tbody tr[data-id="' . $transaction->id . '"] td:nth-child(10)', $formattedValue);
        });
    }

    public function test_details_of_a_sell_transaction_are_correct()
    {
        // Select main test user
        $user = User::firstWhere('email', 'demo@yaffa.cc');

        // Create an investment transaction with specific data
        $transaction = Transaction::factory()
            ->sell([
                'account_id' => AccountEntity::where('name', 'Investment account USD')->first()->id,
                'investment_id' => Investment::where('name', 'Test investment USD')->first()->id,
                'price' => 100,
                'quantity' => 2000,
                'commission' => 300,
                'tax' => 200,
                'dividend' => null,
            ])
            ->create([
                'user_id' => $user->id,
                'comment' => 'Test comment',
                'reconciled' => false,
                'schedule' => true,
                'budget' => false,
            ]);

        // Create the schedule for the transaction
        TransactionSchedule::factory()
            ->create([
                'transaction_id' => $transaction->id,
            ]);

        // Run the test
        $this->browse(function (Browser $browser) use ($user, $transaction) {
            $browser->loginAs($user)
                // Load the list of scheduled transactions
                ->visitRoute('report.schedules')
                // Wait for the table to load, when the placeholder is gone
                ->waitUntilMissing('#table .dataTables_empty', 10)
                // Check that a row with the transaction is present
                ->assertPresent('#table tbody tr[data-id="' . $transaction->id . '"]')
                // The 8th column is the payee, which contains the account name
                ->assertSeeIn('#table tbody tr[data-id="' . $transaction->id . '"] td:nth-child(8)', 'Investment account USD')
                // The 9th column is the category, which contains the investment type
                ->assertSeeIn('#table tbody tr[data-id="' . $transaction->id . '"] td:nth-child(9)', 'Sell');

            // Calculate the formatted value of the transaction using JavaScript
            $value = ($transaction->config->quantity ?? 0) * ($transaction->config->price ?? 0)
                + ($transaction->config->dividend ?? 0)
                - ($transaction->config->commission ?? 0) - ($transaction->config->tax ?? 0);
            $formattedValue = "+ " . $browser
                ->script("const value = {$value};
                    return value.toLocaleString(
                    '{$user->locale}',
                    {
                        style: 'currency',
                        currency: '{$transaction->config->account->config->currency->iso_code}',
                        currencyDisplay: 'narrowSymbol',
                        minimumFractionDigits: {$transaction->config->account->config->currency->num_digits},
                        maximumFractionDigits: {$transaction->config->account->config->currency->num_digits},
                    });")[0];

            // The 10th column is the amount, which contains the formatted value
            $browser->assertSeeIn('#table tbody tr[data-id="' . $transaction->id . '"] td:nth-child(10)', $formattedValue);
        });
    }

    public function test_details_of_a_dividend_transaction_are_correct()
    {
        // Select main test user
        $user = User::firstWhere('email', 'demo@yaffa.cc');

        // Create an investment transaction with specific data
        $transaction = Transaction::factory()
            ->dividend([
                'account_id' => AccountEntity::where('name', 'Investment account USD')->first()->id,
                'investment_id' => Investment::where('name', 'Test investment USD')->first()->id,
                'quantity' => null,
                'price' => null,
                'commission' => 400,
                'tax' => 300,
                'dividend' => 10000,
            ])
            ->create([
                'user_id' => $user->id,
                'comment' => 'Test comment',
                'reconciled' => false,
                'schedule' => true,
                'budget' => false,
            ]);

        // Create the schedule for the transaction
        TransactionSchedule::factory()
            ->create([
                'transaction_id' => $transaction->id,
            ]);

        // Run the test
        $this->browse(function (Browser $browser) use ($user, $transaction) {
            $browser->loginAs($user)
                // Load the list of scheduled transactions
                ->visitRoute('report.schedules')
                // Wait for the table to load, when the placeholder is gone
                ->waitUntilMissing('#table .dataTables_empty', 10)
                // Check that a row with the transaction is present
                ->assertPresent('#table tbody tr[data-id="' . $transaction->id . '"]')
                // The 8th column is the payee, which contains the account name
                ->assertSeeIn('#table tbody tr[data-id="' . $transaction->id . '"] td:nth-child(8)', 'Investment account USD')
                // The 9th column is the category, which contains the investment type
                ->assertSeeIn('#table tbody tr[data-id="' . $transaction->id . '"] td:nth-child(9)', 'Dividend');

            // Calculate the formatted value of the transaction using JavaScript
            $value = ($transaction->config->quantity ?? 0) * ($transaction->config->price ?? 0)
                + ($transaction->config->dividend ?? 0)
                - ($transaction->config->commission ?? 0) - ($transaction->config->tax ?? 0);
            $formattedValue = "+ " . $browser
                ->script("const value = {$value};
                    return value.toLocaleString(
                    '{$user->locale}',
                    {
                        style: 'currency',
                        currency: '{$transaction->config->account->config->currency->iso_code}',
                        currencyDisplay: 'narrowSymbol',
                        minimumFractionDigits: {$transaction->config->account->config->currency->num_digits},
                        maximumFractionDigits: {$transaction->config->account->config->currency->num_digits},
                    });")[0];

            // The 10th column is the amount, which contains the formatted value
            $browser->assertSeeIn('#table tbody tr[data-id="' . $transaction->id . '"] td:nth-child(10)', $formattedValue);
        });
    }
}
