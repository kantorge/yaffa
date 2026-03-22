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
use App\Services\InvestmentService;
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
        $job->handle($this->app->make(InvestmentService::class));

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
        $job->handle($this->app->make(InvestmentService::class));

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
        $job->handle($this->app->make(InvestmentService::class));

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

    /**
     * Regression test: a partial (single-month) account_balance-fact recalculation must not duplicate
     * records for months that fall outside the targeted date range, and must not duplicate the opening
     * balance entry that was written during the initial full recalculation.
     */
    public function test_partial_account_balance_fact_recalculation_does_not_create_duplicate_records(): void
    {
        Carbon::useMonthsOverflow(false);

        /** @var User $user */
        $user = User::factory()->create();

        $payee = AccountEntity::factory()
            ->for($user)
            ->for(Payee::factory()->withUser($user), 'config')
            ->create();

        $account = AccountEntity::factory()
            ->for($user)
            ->for(Account::factory()->withUser($user)->create(['opening_balance' => 1000]), 'config')
            ->create();

        // Create one non-scheduled withdrawal per month for three consecutive months
        $monthMinus2 = now()->startOfMonth()->subMonths(2);
        $monthMinus1 = now()->startOfMonth()->subMonths(1);
        $monthCurrent = now()->startOfMonth();

        foreach ([$monthMinus2, $monthMinus1, $monthCurrent] as $month) {
            Transaction::factory()
                ->for($user)
                ->for(
                    \App\Models\TransactionDetailStandard::factory()->create([
                        'amount_from' => 100,
                        'amount_to' => 100,
                        'account_from_id' => $account->id,
                        'account_to_id' => $payee->id,
                    ]),
                    'config'
                )
                ->make([
                    'date' => $month,
                    'transaction_type' => \App\Enums\TransactionType::WITHDRAWAL->value,
                    'schedule' => false,
                    'budget' => false,
                ])
                ->save();
        }

        // --- Step 1: full recalculation to establish baseline ---
        $fullJob = new CalculateAccountMonthlySummary($user, 'account_balance-fact', $account);
        $fullJob->handle($this->app->make(InvestmentService::class));

        $recordsAfterFull = AccountMonthlySummary::where([
            'user_id' => $user->id,
            'account_entity_id' => $account->id,
            'transaction_type' => 'account_balance',
            'data_type' => 'fact',
        ])->orderBy('date')->get();

        // Expect: opening balance record + one record per transaction month = 4 records total
        $this->assertCount(4, $recordsAfterFull);
        $this->assertEquals(1000, $recordsAfterFull->first()->amount); // opening balance
        $this->assertEquals(-100, $recordsAfterFull->get(1)->amount);
        $this->assertEquals(-100, $recordsAfterFull->get(2)->amount);
        $this->assertEquals(-100, $recordsAfterFull->get(3)->amount);

        // --- Step 2: partial recalculation for just the earliest month ---
        $partialJob = new CalculateAccountMonthlySummary(
            $user,
            'account_balance-fact',
            $account,
            $monthMinus2->clone()->startOfMonth(),
            $monthMinus2->clone()->endOfMonth()
        );
        $partialJob->handle($this->app->make(InvestmentService::class));

        $recordsAfterPartial = AccountMonthlySummary::where([
            'user_id' => $user->id,
            'account_entity_id' => $account->id,
            'transaction_type' => 'account_balance',
            'data_type' => 'fact',
        ])->orderBy('date')->get();

        // Record count must not grow: months outside the targeted range must NOT be duplicated,
        // and the opening balance must NOT be re-inserted.
        $this->assertCount(4, $recordsAfterPartial);

        // Values must match the baseline — no doubling
        $this->assertEquals(1000, $recordsAfterPartial->first()->amount); // opening balance unchanged
        $this->assertEquals(-100, $recordsAfterPartial->get(1)->amount);
        $this->assertEquals(-100, $recordsAfterPartial->get(2)->amount);
        $this->assertEquals(-100, $recordsAfterPartial->get(3)->amount);

        Carbon::resetMonthsOverflow();
    }
}
