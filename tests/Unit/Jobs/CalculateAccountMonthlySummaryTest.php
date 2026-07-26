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
use Illuminate\Support\Facades\DB;
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

    /**
     * Regression test for FR-9 (see .ai/docs/specifications/budget-schedule-redesign/forecast-performance.md):
     * getInvestmentValueForecastData() must fetch each investment's price once per job run, in a
     * fixed number of batched queries, rather than once per investment per forecast month.
     */
    public function test_investment_value_forecast_batches_price_lookups_regardless_of_horizon(): void
    {
        Carbon::useMonthsOverflow(false);

        /** @var User $user */
        $user = User::factory()->create([
            // A 20 year horizon means ~240 forecast months - if the old per-month
            // Investment::find()/getLatestPrice() N+1 ever came back, this would show up
            // immediately as a query count that scales with the horizon length.
            'end_date' => now()->addYears(20),
        ]);

        InvestmentGroup::factory()->for($user)->create();
        $currency = Currency::factory()->for($user)->create();
        $account = AccountEntity::factory()
            ->for($user)
            ->for(Account::factory()
                ->withUser($user)
                ->create(['currency_id' => $currency->id]), 'config')
            ->create();
        $investment = Investment::factory()
            ->for($user)
            ->withUser($user)
            ->create(['currency_id' => $currency->id]);

        /** @var Transaction $investmentTransaction */
        $investmentTransaction = Transaction::factory()
            ->for($user)
            ->buy_schedule($user, [
                'account_id' => $account->id,
                'investment_id' => $investment->id,
                'price' => 10,
                'quantity' => 5,
                'commission' => null,
                'tax' => null,
                'dividend' => null,
            ])
            ->create();

        $investmentTransaction->transactionSchedule->update([
            'start_date' => now()->startOfMonth(),
            'next_date' => now()->startOfMonth(),
            'end_date' => null,
            'count' => null,
            'interval' => 1,
            'frequency' => 'MONTHLY',
        ]);

        // A stored price, so the batched "combined" price lookup has something to resolve for
        // every forecast month (a schedule-only transaction is not itself a priced fact transaction).
        \App\Models\InvestmentPrice::factory()->create([
            'investment_id' => $investment->id,
            'date' => now()->subDay(),
            'price' => 10,
        ]);

        DB::enableQueryLog();

        $job = new CalculateAccountMonthlySummary($user, 'investment_value-forecast', $account);
        $job->handle($this->app->make(InvestmentService::class));

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $investmentTableQueries = array_filter(
            $queries,
            fn (array $q) => str_contains($q['query'], '`investments`')
        );

        $this->assertCount(
            1,
            $investmentTableQueries,
            'Expected exactly one batched investments lookup query regardless of forecast horizon length.'
        );

        // The whole job (schedule loading, batched price/transaction lookups, the insert) stays a
        // small, fixed number of queries - nowhere near the ~240 months in this horizon.
        $this->assertLessThan(20, count($queries));

        // The forecast values themselves must still be correct: 5 shares bought/month, at a
        // fixed price of 10 (no InvestmentPrice/other transaction ever overrides it), cumulating
        // month over month.
        $summaryRecords = AccountMonthlySummary::where([
            'user_id' => $user->id,
            'account_entity_id' => $account->id,
            'transaction_type' => 'investment_value',
            'data_type' => 'forecast',
        ])->orderBy('date')->get();

        $this->assertGreaterThan(50, $summaryRecords->count());
        $summaryRecords->each(function ($summaryRecord, $index) {
            $this->assertEqualsWithDelta(($index + 2) * 5 * 10, $summaryRecord->amount, 0.001);
        });

        Carbon::resetMonthsOverflow();
    }

    /**
     * Regression test for FR-9: the batched price lookup must still honor "latest price on or
     * before this month's cutoff date" semantics per forecast month, so a price recorded
     * mid-month is picked up starting with the forecast month it falls in and carries forward
     * into every later month, not just the month it happened to land in.
     */
    public function test_investment_value_forecast_reflects_a_mid_month_price_update_in_following_months(): void
    {
        Carbon::useMonthsOverflow(false);

        /** @var User $user */
        $user = User::factory()->create([
            'end_date' => now()->addYears(2),
        ]);

        InvestmentGroup::factory()->for($user)->create();
        $currency = Currency::factory()->for($user)->create();
        $account = AccountEntity::factory()
            ->for($user)
            ->for(Account::factory()
                ->withUser($user)
                ->create(['currency_id' => $currency->id]), 'config')
            ->create();
        $investment = Investment::factory()
            ->for($user)
            ->withUser($user)
            ->create(['currency_id' => $currency->id]);

        /** @var Transaction $investmentTransaction */
        $investmentTransaction = Transaction::factory()
            ->for($user)
            ->buy_schedule($user, [
                'account_id' => $account->id,
                'investment_id' => $investment->id,
                'price' => 10,
                'quantity' => 5,
                'commission' => null,
                'tax' => null,
                'dividend' => null,
            ])
            ->create();

        $investmentTransaction->transactionSchedule->update([
            'start_date' => now()->startOfMonth(),
            'next_date' => now()->startOfMonth(),
            'end_date' => null,
            'count' => null,
            'interval' => 1,
            'frequency' => 'MONTHLY',
        ]);

        // Baseline price, well before the forecast horizon.
        \App\Models\InvestmentPrice::factory()->create([
            'investment_id' => $investment->id,
            'date' => now()->subMonths(2),
            'price' => 10,
        ]);

        // A second price recorded mid-month, landing inside the second forecast month
        // (forecast index 1 = now()->addMonths(2)).
        \App\Models\InvestmentPrice::factory()->create([
            'investment_id' => $investment->id,
            'date' => now()->addMonths(2)->day(15),
            'price' => 20,
        ]);

        $job = new CalculateAccountMonthlySummary($user, 'investment_value-forecast', $account);
        $job->handle($this->app->make(InvestmentService::class));

        $summaryRecords = AccountMonthlySummary::where([
            'user_id' => $user->id,
            'account_entity_id' => $account->id,
            'transaction_type' => 'investment_value',
            'data_type' => 'forecast',
        ])->orderBy('date')->get();

        // Same quantity progression as the sibling test above: (index + 2) * 5 shares by
        // forecast month `index`.
        $quantityAt = fn (int $index) => ($index + 2) * 5;

        // Index 0 (1 month out) is still before the mid-month price update: baseline price (10).
        $this->assertEqualsWithDelta($quantityAt(0) * 10, $summaryRecords[0]->amount, 0.001);
        // Index 1 (2 months out) is the month the update lands in: new price (20) applies already.
        $this->assertEqualsWithDelta($quantityAt(1) * 20, $summaryRecords[1]->amount, 0.001);
        // Index 2 (3 months out): the update carries forward, not just a one-month blip.
        $this->assertEqualsWithDelta($quantityAt(2) * 20, $summaryRecords[2]->amount, 0.001);

        Carbon::resetMonthsOverflow();
    }
}
