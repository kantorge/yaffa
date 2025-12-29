<?php

namespace Tests\Unit\Jobs;

use App\Jobs\CalculateAccountMonthlySummary;
use App\Models\Account;
use App\Models\AccountEntity;
use App\Models\AccountMonthlySummary;
use App\Models\Currency;
use App\Models\Investment;
use App\Models\InvestmentGroup;
use App\Models\Payee;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalculateAccountMonthlySummaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_standard_transactions_account_balance_forecast(): void
    {
        Carbon::useMonthsOverflow(false);

        // Create a user and all necessary assets for a transaction
        /** @var User $user */
        $user = User::factory()->create([
            'end_date' => now()->addMonths(12)->endOfMonth(),
        ]);

        $account = AccountEntity::factory()
            ->for($user)
            ->for(Account::factory()->withUser($user), 'config')
            ->create();
        AccountEntity::factory()
            ->for($user)
            ->for(Payee::factory()->withUser($user), 'config')
            ->create();

        // Create a scheduled transaction
        /** @var Transaction $transaction */
        $transaction = Transaction::factory()
            ->for($user)
            ->withdrawal_schedule($user)
            ->create();

        // Adjust the amount to better suite our test
        $transaction->config()->update([
            'amount_from' => 100,
            'amount_to' => 100,
        ]);

        // By default, a transaction schedule will be created
        // We need to adjust its properties to better suite our test
        $transaction->transactionSchedule->update([
            'start_date' => now()->startOfMonth()->subMonths(2),
            'next_date' => now()->startOfMonth()->subMonths(2),
            'end_date' => now()->addMonths(9)->endOfMonth(),
            'count' => null,
            'interval' => 1,
            'frequency' => 'MONTHLY',
        ]);

        // Run the job
        $job = new CalculateAccountMonthlySummary($user, 'account_balance-forecast', $account);
        $job->handle();

        // Get the summary values from the database for the account and the data type
        $summaryRecords = AccountMonthlySummary::where([
            'user_id' => $user->id,
            'account_entity_id' => $account->id,
            'transaction_type' => 'account_balance',
            'data_type' => 'forecast',
        ])
            ->get();

        // As the start date is used as the first date, we should have 12 records
        $this->assertCount(12, $summaryRecords);

        // Loop through the summary records and check that the date and the amount is correct
        $summaryRecords->each(function ($summaryRecord, $index) {
            $this->assertEquals($summaryRecord->date, now()->subMonths(2)->startOfMonth()->addMonths($index));
            $this->assertEquals($summaryRecord->amount, -100);
        });

        // Now, let's update the transaction, recalculate the summary and check the results
        $transaction->config()->update([
            'amount_from' => 200,
            'amount_to' => 200,
        ]);

        // Run the job
        $job = new CalculateAccountMonthlySummary($user, 'account_balance-forecast', $account);
        $job->handle();

        // Get the summary values from the database for the account and the data type
        $summaryRecords = AccountMonthlySummary::where([
            'user_id' => $user->id,
            'account_entity_id' => $account->id,
            'transaction_type' => 'account_balance',
            'data_type' => 'forecast',
        ])
            ->get();

        // The earlier records should be removed, so we should have 12 records
        $this->assertCount(12, $summaryRecords);

        // Loop through the summary records and check that the date and the amount is correct
        $summaryRecords->each(function ($summaryRecord, $index) {
            $this->assertEquals($summaryRecord->date, now()->subMonths(2)->startOfMonth()->addMonths($index));
            $this->assertEquals($summaryRecord->amount, -200);
        });

        Carbon::resetMonthsOverflow();
    }

    public function test_combination_of_standard_and_investment_transactions_account_balance_forecast(): void
    {
        Carbon::useMonthsOverflow(false);

        // Create a user and all necessary assets for a transaction
        /** @var User $user */
        $user = User::factory()->create([
            'end_date' => now()->addMonths(12)->endOfMonth(),
        ]);

        InvestmentGroup::factory()->for($user)->create();
        $currency = Currency::factory()->for($user)->create();
        $account = AccountEntity::factory()
            ->for($user)
            ->for(Account::factory()
                ->withUser($user)
                ->create(['currency_id' => $currency->id]), 'config')
            ->create();
        AccountEntity::factory()->for($user)->for(Payee::factory()->withUser($user), 'config')->create();
        $investment = Investment::factory()
            ->for($user)
            ->withUser($user)
            ->create([
                'currency_id' => $currency->id,
            ]);


        // Create a scheduled standard transaction
        /** @var Transaction $transaction */
        $transaction = Transaction::factory()
            ->for($user)
            ->withdrawal_schedule($user)
            ->create();

        // Adjust the amount to better suite our test
        $transaction->config()->update([
            'account_from_id' => $account->id,
            'amount_from' => 100,
            'amount_to' => 100,
        ]);

        // By default, a transaction schedule will be created
        // We need to adjust its properties to better suite our test
        $transaction->transactionSchedule->update([
            'start_date' => now()->startOfMonth()->subMonths(2),
            'next_date' => now()->startOfMonth()->subMonths(2),
            'end_date' => now()->endOfMonth()->addMonths(9),
            'count' => null,
            'interval' => 1,
            'frequency' => 'MONTHLY',
        ]);

        // Now, let's create an investment transaction, which partly overlaps with the standard transaction schedule
        /** @var Transaction $investmentTransaction */
        $investmentTransaction = Transaction::factory()
            ->for($user)
            ->dividend_schedule($user, [
                'dividend' => 50,
                'commission' => null,
                'tax' => null,
                'account_id' => $account->id,
                'investment_id' => $investment->id,
            ])
            ->create()
            ->load('config');

        // By default, a transaction schedule will be created
        // We need to adjust its properties to better suite our test
        $investmentTransaction->transactionSchedule->update([
            'start_date' => now()->startOfMonth(),
            'next_date' => now()->startOfMonth(),
            'end_date' => now()->endOfMonth()->addMonths(11),
            'count' => null,
            'interval' => 1,
            'frequency' => 'MONTHLY',
        ]);

        // Run the job
        $job = new CalculateAccountMonthlySummary($user, 'account_balance-forecast', $account);
        $job->handle();

        // Get the summary values from the database for the account and the data type
        $summaryRecords = AccountMonthlySummary::where([
            'user_id' => $user->id,
            'account_entity_id' => $account->id,
            'transaction_type' => 'account_balance',
            'data_type' => 'forecast',
        ])
            ->get();

        // When observing the number of records, we need to take into account the partial overlap
        $this->assertCount(14, $summaryRecords);

        // Define the expected values for the standard transaction
        $expectedBalance = [-100, -100, -50, -50, -50, -50, -50, -50, -50, -50, -50, -50, 50, 50];

        // Loop through the summary records and check that the date and the amount is correct
        $summaryRecords->each(function ($summaryRecord, $index) use ($expectedBalance) {
            $this->assertEquals($summaryRecord->date, now()->subMonths(2)->startOfMonth()->addMonths($index));
            $this->assertEquals($summaryRecord->amount, $expectedBalance[$index]);
        });

        Carbon::resetMonthsOverflow();
    }
}
