<?php

namespace Tests\Unit\Jobs;

use App\Jobs\CalculateAccountMonthlySummary;
use App\Models\Account;
use App\Models\AccountEntity;
use App\Models\AccountMonthlySummary;
use App\Models\Budget;
use App\Models\Category;
use App\Models\Currency;
use App\Models\Investment;
use App\Models\InvestmentGroup;
use App\Models\Payee;
use App\Models\Transaction;
use App\Models\User;
use App\Services\BudgetService;
use App\Services\InvestmentService;
use Brick\Math\BigDecimal;
use Brick\Money\Money;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CalculateAccountMonthlySummaryTest extends TestCase
{
    use RefreshDatabase;

    /**
     * AccountMonthlySummary::amount is now Money-cast (FR-7); compare its exact amount
     * rather than relying on PHP's loose float equality.
     *
     * Builds the expected string via BigDecimal rather than number_format(): number_format()
     * round-trips through a double and can misrender an exact value at higher scales (see
     * the equivalent note on AccountMonthlySummaryTest::assertBalanceFactEquals()) -
     * reintroducing the float-precision bug class this assertion exists to catch.
     */
    private function assertSummaryAmountEquals(string|int $expected, Money $actual): void
    {
        $scale = $actual->getAmount()->getScale();

        $this->assertSame(
            (string) BigDecimal::of($expected)->toScale($scale),
            (string) $actual->getAmount()
        );
    }

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
        $job->handle($this->app->make(InvestmentService::class), $this->app->make(BudgetService::class));

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
            $this->assertSummaryAmountEquals(-100, $summaryRecord->amount);
        });

        // Now, let's update the transaction, recalculate the summary and check the results
        $transaction->config()->update([
            'amount_from' => 200,
            'amount_to' => 200,
        ]);

        // Run the job
        $job = new CalculateAccountMonthlySummary($user, 'account_balance-forecast', $account);
        $job->handle($this->app->make(InvestmentService::class), $this->app->make(BudgetService::class));

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
            $this->assertSummaryAmountEquals(-200, $summaryRecord->amount);
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
        $job->handle($this->app->make(InvestmentService::class), $this->app->make(BudgetService::class));

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
            $this->assertSummaryAmountEquals($expectedBalance[$index], $summaryRecord->amount);
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
                ])
                ->save();
        }

        // --- Step 1: full recalculation to establish baseline ---
        $fullJob = new CalculateAccountMonthlySummary($user, 'account_balance-fact', $account);
        $fullJob->handle($this->app->make(InvestmentService::class), $this->app->make(BudgetService::class));

        $recordsAfterFull = AccountMonthlySummary::where([
            'user_id' => $user->id,
            'account_entity_id' => $account->id,
            'transaction_type' => 'account_balance',
            'data_type' => 'fact',
        ])->orderBy('date')->get();

        // Expect: opening balance record + one record per transaction month = 4 records total
        $this->assertCount(4, $recordsAfterFull);
        $this->assertSummaryAmountEquals(1000, $recordsAfterFull->first()->amount); // opening balance
        $this->assertSummaryAmountEquals(-100, $recordsAfterFull->get(1)->amount);
        $this->assertSummaryAmountEquals(-100, $recordsAfterFull->get(2)->amount);
        $this->assertSummaryAmountEquals(-100, $recordsAfterFull->get(3)->amount);

        // --- Step 2: partial recalculation for just the earliest month ---
        $partialJob = new CalculateAccountMonthlySummary(
            $user,
            'account_balance-fact',
            $account,
            $monthMinus2->clone()->startOfMonth(),
            $monthMinus2->clone()->endOfMonth()
        );
        $partialJob->handle($this->app->make(InvestmentService::class), $this->app->make(BudgetService::class));

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
        $this->assertSummaryAmountEquals(1000, $recordsAfterPartial->first()->amount); // opening balance unchanged
        $this->assertSummaryAmountEquals(-100, $recordsAfterPartial->get(1)->amount);
        $this->assertSummaryAmountEquals(-100, $recordsAfterPartial->get(2)->amount);
        $this->assertSummaryAmountEquals(-100, $recordsAfterPartial->get(3)->amount);

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
        $job->handle($this->app->make(InvestmentService::class), $this->app->make(BudgetService::class));

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
            $this->assertSummaryAmountEquals(($index + 2) * 5 * 10, $summaryRecord->amount);
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
        $job->handle($this->app->make(InvestmentService::class), $this->app->make(BudgetService::class));

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
        $this->assertSummaryAmountEquals($quantityAt(0) * 10, $summaryRecords[0]->amount);
        // Index 1 (2 months out) is the month the update lands in: new price (20) applies already.
        $this->assertSummaryAmountEquals($quantityAt(1) * 20, $summaryRecords[1]->amount);
        // Index 2 (3 months out): the update carries forward, not just a one-month blip.
        $this->assertSummaryAmountEquals($quantityAt(2) * 20, $summaryRecords[2]->amount);

        Carbon::resetMonthsOverflow();
    }

    /**
     * FR-8 wiring coverage: the account-balance forecast bucket must apply each schedule's
     * inflation-compounded multiplier (computed in Transaction::scheduleInstances()) to the
     * amounts it sums, stepping up at the calendar-year boundary rather than never at all.
     */
    public function test_account_balance_forecast_compounds_at_the_next_calendar_year_boundary(): void
    {
        Carbon::useMonthsOverflow(false);

        /** @var User $user */
        $user = User::factory()->create([
            'end_date' => now()->addMonths(14)->endOfMonth(),
        ]);

        $account = AccountEntity::factory()
            ->for($user)
            ->for(Account::factory()->withUser($user), 'config')
            ->create();
        AccountEntity::factory()
            ->for($user)
            ->for(Payee::factory()->withUser($user), 'config')
            ->create();

        $scheduleStart = now()->startOfMonth()->subMonths(2);

        /** @var Transaction $transaction */
        $transaction = Transaction::factory()
            ->for($user)
            ->withdrawal_schedule($user)
            ->create();

        $transaction->config()->update([
            'amount_from' => 100,
            'amount_to' => 100,
        ]);

        $transaction->transactionSchedule->update([
            'start_date' => $scheduleStart,
            'next_date' => $scheduleStart,
            'end_date' => now()->addMonths(11)->endOfMonth(),
            'count' => null,
            'interval' => 1,
            'frequency' => 'MONTHLY',
            'inflation' => 10.0,
        ]);

        $job = new CalculateAccountMonthlySummary($user, 'account_balance-forecast', $account);
        $job->handle($this->app->make(InvestmentService::class), $this->app->make(BudgetService::class));

        $summaryRecords = AccountMonthlySummary::where([
            'user_id' => $user->id,
            'account_entity_id' => $account->id,
            'transaction_type' => 'account_balance',
            'data_type' => 'forecast',
        ])->orderBy('date')->get();

        // The 14-month window always crosses exactly one January 1st, so every record's year is
        // either the schedule's start year (no compounding yet) or exactly one year later
        // (compounded once).
        $this->assertGreaterThan(0, $summaryRecords->count());
        $summaryRecords->each(function ($summaryRecord) use ($scheduleStart) {
            $expectedMultiplier = $summaryRecord->date->year > $scheduleStart->year ? 1.1 : 1.0;
            $this->assertEqualsWithDelta(-100 * $expectedMultiplier, $summaryRecord->amount->getAmount()->toFloat(), 0.001);
        });

        Carbon::resetMonthsOverflow();
    }

    /**
     * FR-3 coverage: the account-balance budget bucket (task 'account_balance-budget') now reads
     * only from active, standalone Budget rows, attributed per Budget.account_id - an
     * account-scoped row feeds only that account's own bucket, an account-agnostic row feeds
     * only the null-account bucket, and an inactive Budget contributes to neither.
     */
    public function test_account_balance_budget_reads_from_active_standalone_budgets_attributed_per_account(): void
    {
        Carbon::useMonthsOverflow(false);

        /** @var User $user */
        $user = User::factory()->create([
            'end_date' => now()->addMonths(3)->endOfMonth(),
        ]);

        $account = AccountEntity::factory()
            ->for($user)
            ->for(Account::factory()->withUser($user), 'config')
            ->create();

        $category = Category::factory()->for($user)->create();

        Budget::factory()->create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'account_id' => $account->id,
            'transaction_type' => 'withdrawal',
            'amount' => 200,
            'frequency' => 'MONTHLY',
            'interval' => 1,
            'start_date' => now()->startOfMonth(),
            'end_date' => null,
            'count' => null,
            'inflation' => null,
        ]);

        Budget::factory()->create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'account_id' => null,
            'transaction_type' => 'deposit',
            'amount' => 400,
            'frequency' => 'MONTHLY',
            'interval' => 1,
            'start_date' => now()->startOfMonth(),
            'end_date' => null,
            'count' => null,
            'inflation' => null,
        ]);

        // Inactive (already exhausted): must contribute to neither bucket.
        Budget::factory()->create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'account_id' => $account->id,
            'transaction_type' => 'withdrawal',
            'amount' => 999,
            'frequency' => 'MONTHLY',
            'interval' => 1,
            'start_date' => now()->subYears(2),
            'end_date' => now()->subYear(),
            'count' => null,
            'inflation' => null,
        ]);

        // Run for the specific account: only its own $200 withdrawal budget should count.
        $accountJob = new CalculateAccountMonthlySummary($user, 'account_balance-budget', $account);
        $accountJob->handle($this->app->make(InvestmentService::class), $this->app->make(BudgetService::class));

        $accountRecords = AccountMonthlySummary::where([
            'user_id' => $user->id,
            'account_entity_id' => $account->id,
            'transaction_type' => 'account_balance',
            'data_type' => 'budget',
        ])->orderBy('date')->get();

        $this->assertGreaterThan(0, $accountRecords->count());
        $accountRecords->each(function ($record) {
            $this->assertEqualsWithDelta(-200.0, $record->amount->getAmount()->toFloat(), 0.001);
        });

        // Run the account-agnostic bucket (no account provided): only the $400 deposit budget.
        $agnosticJob = new CalculateAccountMonthlySummary($user, 'account_balance-budget');
        $agnosticJob->handle($this->app->make(InvestmentService::class), $this->app->make(BudgetService::class));

        $agnosticRecords = AccountMonthlySummary::where([
            'user_id' => $user->id,
            'transaction_type' => 'account_balance',
            'data_type' => 'budget',
        ])->whereNull('account_entity_id')->orderBy('date')->get();

        $this->assertGreaterThan(0, $agnosticRecords->count());
        $agnosticRecords->each(function ($record) {
            $this->assertEqualsWithDelta(400.0, $record->amount->getAmount()->toFloat(), 0.001);
        });

        Carbon::resetMonthsOverflow();
    }

    /**
     * FR-8 coverage: the budget bucket compounds each Budget row's own inflation rate at the
     * calendar-year boundary, same as the forecast bucket.
     */
    public function test_account_balance_budget_compounds_at_the_next_calendar_year_boundary(): void
    {
        Carbon::useMonthsOverflow(false);

        /** @var User $user */
        $user = User::factory()->create([
            'end_date' => now()->addMonths(14)->endOfMonth(),
        ]);

        $account = AccountEntity::factory()
            ->for($user)
            ->for(Account::factory()->withUser($user), 'config')
            ->create();

        $category = Category::factory()->for($user)->create();

        $budgetStart = now()->startOfMonth();

        Budget::factory()->create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'account_id' => $account->id,
            'transaction_type' => 'withdrawal',
            'amount' => 100,
            'frequency' => 'MONTHLY',
            'interval' => 1,
            'start_date' => $budgetStart,
            'end_date' => now()->addMonths(13)->endOfMonth(),
            'count' => null,
            'inflation' => 10.0,
        ]);

        $job = new CalculateAccountMonthlySummary($user, 'account_balance-budget', $account);
        $job->handle($this->app->make(InvestmentService::class), $this->app->make(BudgetService::class));

        $records = AccountMonthlySummary::where([
            'user_id' => $user->id,
            'account_entity_id' => $account->id,
            'transaction_type' => 'account_balance',
            'data_type' => 'budget',
        ])->orderBy('date')->get();

        $this->assertGreaterThan(0, $records->count());
        $records->each(function ($record) use ($budgetStart) {
            $expectedMultiplier = $record->date->year > $budgetStart->year ? 1.1 : 1.0;
            $this->assertEqualsWithDelta(-100 * $expectedMultiplier, $record->amount->getAmount()->toFloat(), 0.001);
        });

        Carbon::resetMonthsOverflow();
    }
}
