<?php

namespace App\Jobs;

use App\Enums\TransactionType as TransactionTypeEnum;
use App\Http\Traits\ScheduleTrait;
use App\Models\Account;
use App\Models\AccountEntity;
use App\Models\AccountMonthlySummary;
use App\Models\Budget;
use App\Models\Investment;
use App\Models\Transaction;
use App\Models\TransactionDetailInvestment;
use App\Models\TransactionDetailStandard;
use App\Models\User;
use App\Services\BudgetService;
use App\Services\InflationCalculator;
use App\Services\InvestmentService;
use App\Support\ScheduleInstance;
use Brick\Math\BigDecimal;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Closure;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class CalculateAccountMonthlySummary implements ShouldQueue
{
    use Batchable;
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use ScheduleTrait;
    use SerializesModels;

    private User $user;
    private ?AccountEntity $accountEntity;
    private string $task;
    private ?Carbon $dateFrom;
    private ?Carbon $dateTo;
    private InvestmentService $investmentService;
    private BudgetService $budgetService;

    public int $timeout = 240;

    /**
     * Create a new job instance.
     */
    public function __construct(
        User $user,
        string $task,
        ?AccountEntity $accountEntity = null,
        ?Carbon $dateFrom = null,
        ?Carbon $dateTo = null
    ) {
        // The user is always required, but used only for the budget task, where no account is provided
        $this->user = $user;
        $this->accountEntity = $accountEntity?->load('config');
        $this->task = $task;
        $this->dateFrom = $dateFrom;
        $this->dateTo = $dateTo;
    }

    /**
     * Execute the job.
     */
    public function handle(InvestmentService $investmentService, BudgetService $budgetService): void
    {
        $this->investmentService = $investmentService;
        $this->budgetService = $budgetService;

        switch ($this->task) {
            case 'account_balance-fact':
                $this->handleAccountBalanceFact();
                break;
            case 'account_balance-forecast':
                $this->handleAccountBalanceForecast();
                break;
            case 'investment_value-fact':
                $this->handleInvestmentValueFact();
                break;
            case 'investment_value-forecast':
                $this->handleInvestmentValueForecast();
                break;
            case 'account_balance-budget':
                $this->handleAccountBalanceBudget();
                break;
            default:
                // At the moment, we don't expect any other tasks
                break;
        }
    }

    private function handleAccountBalanceFact(): void
    {
        // Purge existing data for this account and data type
        AccountMonthlySummary::where('account_entity_id', $this->accountEntity->id)
            ->where('transaction_type', 'account_balance')
            ->where('data_type', 'fact')
            // Optionally, only delete data between the given dates
            ->when(
                $this->dateFrom,
                fn ($query) => $query->where('date', '>=', $this->dateFrom)
            )
            ->when(
                $this->dateTo,
                fn ($query) => $query->where('date', '<=', $this->dateTo)
            )
            ->delete();

        // Get data (Collection of arrays) and perform the batch insert
        AccountMonthlySummary::insert(
            $this->getAccountBalanceFactData()->toArray()
        );
    }

    private function handleAccountBalanceForecast(): void
    {
        // Purge existing data for this account and data type
        AccountMonthlySummary::where('account_entity_id', $this->accountEntity->id)
            ->where('transaction_type', 'account_balance')
            ->where('data_type', 'forecast')
            // Forecast data is always recalculated from the start date
            ->delete();

        // Get data (Collection of arrays) and perform the batch insert
        AccountMonthlySummary::insert(
            $this->getAccountBalanceForecastData()->toArray()
        );
    }

    private function handleInvestmentValueFact(): void
    {
        // Purge existing data for this account and data type
        AccountMonthlySummary::where('account_entity_id', $this->accountEntity->id)
            ->where('transaction_type', 'investment_value')
            ->where('data_type', 'fact')
            // Investment fact data is always recalculated from the start date until the latest relevant month
            ->delete();

        // Get data (Collection of arrays) and perform the batch insert
        AccountMonthlySummary::insert(
            $this->getInvestmentValueFactData()->toArray()
        );
    }

    private function handleInvestmentValueForecast(): void
    {
        // Purge existing data for this account and data type
        AccountMonthlySummary::where('account_entity_id', $this->accountEntity->id)
            ->where('transaction_type', 'investment_value')
            ->where('data_type', 'forecast')
            // Forecast data is always recalculated from the start date
            ->delete();

        // Get data (Collection of arrays) and perform the batch insert
        AccountMonthlySummary::insert(
            $this->getInvestmentValueForecastData()->toArray()
        );
    }

    private function handleAccountBalanceBudget(): void
    {
        // Purge existing data for this account and data type
        AccountMonthlySummary::when(
            $this->accountEntity,
            fn ($query) => $query->where('account_entity_id', $this->accountEntity->id),
            fn ($query) => $query->whereNull('account_entity_id')
        )
            ->where('user_id', $this->user->id)
            ->where('transaction_type', 'account_balance')
            ->where('data_type', 'budget')
            // Budget data is always recalculated from the start date
            ->delete();

        // Get data (Collection of arrays) and perform the batch insert
        AccountMonthlySummary::insert(
            $this->getAccountBalanceBudgetData()->toArray()
        );
    }

    /**
     * Get the monthly summary data for standard transactions for the account (accountEntity) provided at class level.
     * The function loops through all months between the first and last transaction associated with the account.
     * and also prepends the opening balance as the first available month.
     *
     * When dateFrom and dateTo are both provided (partial recalculation), only data within that range is generated
     * and the opening balance is not added again (it was already stored during a previous full recalculation).
     */
    private function getAccountBalanceFactData(): Collection
    {
        // Get the dates of the first and last transaction for this account.
        // When dateTo is set, the period is capped at that date to avoid regenerating and duplicating
        // records for months that are outside the targeted recalculation range.
        $firstTransactionDate = $this->dateFrom ??
            Carbon::parse($this->accountEntity->allTransactionDates()->min('date'));
        $lastTransactionDate = $this->dateTo ??
            Carbon::parse($this->accountEntity->allTransactionDates()->max('date'));

        // Loop through all months between the first and last transaction, using the first day of the month
        $period = CarbonPeriod::between(
            $firstTransactionDate->startOfMonth(),
            $lastTransactionDate->endOfMonth()
        )
            ->months();

        $results = new Collection();

        foreach ($period as $month) {
            // Create a Carbon instance of the month
            $carbonMonth = Carbon::instance($month);

            $amount = AccountMonthlySummary::calculateAccountBalanceFact(
                $this->accountEntity,
                $carbonMonth
            );

            // Don't store zero values
            if ($amount->isZero()) {
                continue;
            }

            // Push new data to the results collection, which represents an AccountMonthlySummary model
            $results->push([
                'date' => $carbonMonth,
                'user_id' => $this->accountEntity->user_id,
                'account_entity_id' => $this->accountEntity->id,
                'transaction_type' => 'account_balance',
                'data_type' => 'fact',
                'amount' => (string) $amount,
            ]);
        }

        // Only add the opening balance for a full (non-partial) recalculation.
        // When dateFrom is set (partial recalculation), the opening balance already exists from a prior
        // full recalculation and must not be inserted again to avoid duplicating it.
        if ($this->dateFrom === null) {
            if (count($results) > 0) {
                $newDate = $firstTransactionDate->subMonth();
            } else {
                $newDate = Carbon::now()->startOfMonth();
            }

            $results->prepend([
                'date' => $newDate,
                'user_id' => $this->accountEntity->user_id,
                'account_entity_id' => $this->accountEntity->id,
                'transaction_type' => 'account_balance',
                'data_type' => 'fact',
                'amount' => $this->accountEntity->config instanceof Account
                    ? (string) $this->accountEntity->config->opening_balance->getAmount()
                    : '0',
            ]);
        }

        return $results;
    }

    private function getAccountBalanceForecastData(): Collection
    {
        // Get all active scheduled standard transactions for this account
        $scheduledStandardTransactions = Transaction::with([
            'config',
            'transactionSchedule',
        ])
            ->byType('standard')
            ->isSchedule()
            ->whereHas(
                'transactionSchedule',
                fn ($query) => $query->where('active', true)
            )
            ->whereHasMorph(
                'config',
                TransactionDetailStandard::class,
                fn ($query) => $query
                    ->where('account_from_id', $this->accountEntity->id)
                    ->orWhere('account_to_id', $this->accountEntity->id)
            )
            ->get();

        // Get all active scheduled investment transactions for this account
        $scheduledInvestmentTransactions = Transaction::with([
            'config',
            'transactionSchedule',
        ])
            ->byType('investment')
            ->isSchedule()
            ->whereHas(
                'transactionSchedule',
                fn ($query) => $query->where('active', true)
            )
            ->whereHasMorph(
                'config',
                TransactionDetailInvestment::class,
                fn ($query) => $query->where('account_id', $this->accountEntity->id)
            )
            ->get();

        // If no scheduled transactions were found, we can return an empty collection
        if ($scheduledStandardTransactions->isEmpty() && $scheduledInvestmentTransactions->isEmpty()) {
            return new Collection();
        }

        // Get all instances of the transactions, added to new collections of transactions
        $scheduledStandardTransactionInstances = $this->getScheduleInstances(
            $scheduledStandardTransactions,
            'next'
        );
        $scheduledInvestmentTransactionInstances = $this->getScheduleInstances(
            $scheduledInvestmentTransactions,
            'next'
        );

        // Convert the transaction dates to 'Y-m-01' format and group by the formatted date
        $scheduledStandardTransactionInstances = $scheduledStandardTransactionInstances
            ->groupBy(fn ($transaction) => Carbon::parse($transaction->date)->format('Y-m-01'));
        $scheduledInvestmentTransactionInstances = $scheduledInvestmentTransactionInstances
            ->groupBy(fn ($transaction) => Carbon::parse($transaction->date)->format('Y-m-01'));

        // Collect the list of months from both types of transactions, and create a unique list, sorted
        $monthsToLoop = collect(array_merge(
            $scheduledStandardTransactionInstances->keys()->toArray(),
            $scheduledInvestmentTransactionInstances->keys()->toArray()
        ))
            ->unique()
            ->sort();

        $results = new Collection();

        // Loop through the grouped standard transactions
        foreach ($monthsToLoop as $month) {
            // Get the investment transactions for the same month
            $investmentTransactions = $scheduledInvestmentTransactionInstances[$month] ?? collect();

            // Split the transactions into from and to transactions
            [$transactionsFrom, $transactionsTo] = ($scheduledStandardTransactionInstances[$month] ?? collect())->partition(
                fn (ScheduleInstance $transaction) =>
                    $transaction->config instanceof TransactionDetailStandard
                    && $transaction->config->account_from_id === $this->accountEntity->id
            );

            // FR-8: each instance carries its own inflation-compounded multiplier (computed once,
            // in Transaction::scheduleInstances(), from the schedule's own inflation rate).
            // Summed exactly via sumBigDecimal: multiplying a Money's BigDecimal amount by the
            // multiplier here already drops the Money wrapper down to a plain BigDecimal.
            $amountFrom = $this->sumBigDecimal(
                $transactionsFrom,
                fn (ScheduleInstance $transaction) => $transaction->config?->amount_from
                    ?->getAmount()->multipliedBy($transaction->inflationMultiplier)
            );
            $amountTo = $this->sumBigDecimal(
                $transactionsTo,
                fn (ScheduleInstance $transaction) => $transaction->config?->amount_to
                    ?->getAmount()->multipliedBy($transaction->inflationMultiplier)
            );
            $amountInvestment = $this->sumBigDecimal(
                $investmentTransactions,
                fn (ScheduleInstance $transaction) => $transaction->cashflow_value
                    ?->getAmount()->multipliedBy($transaction->inflationMultiplier)
            );

            $amount = $amountInvestment->plus($amountTo)->minus($amountFrom);

            // Don't store zero values
            if ($amount->isZero()) {
                continue;
            }

            $results->push([
                'date' => Carbon::createFromFormat('Y-m-d', $month),
                'user_id' => $this->accountEntity->user_id,
                'account_entity_id' => $this->accountEntity->id,
                'transaction_type' => 'account_balance',
                'data_type' => 'forecast',
                'amount' => (string) $amount,
            ]);
        }
        return $results;
    }

    /**
     * Sum a BigDecimal-returning extractor across a collection exactly, skipping null
     * results (e.g. no price found for an investment/date pair), instead of via
     * Collection::sum() (which does native `+=` and can't handle Money/BigDecimal values at
     * all, let alone exactly).
     *
     * @param  iterable<int|string, mixed>  $items
     * @param  Closure(mixed, int|string): ?BigDecimal  $extractor
     */
    private function sumBigDecimal(iterable $items, Closure $extractor): BigDecimal
    {
        $sum = BigDecimal::zero();

        foreach ($items as $key => $item) {
            $value = $extractor($item, $key);

            if ($value === null) {
                continue;
            }

            $sum = $sum->plus($value);
        }

        return $sum;
    }

    /**
     * Get the monthly summary data for investment transactions for the account (accountEntity) provided at class level.
     * The function loops through all months between the first and last transaction.
     */
    private function getInvestmentValueFactData(): Collection
    {
        // Return an empty collection if the account has no investment transactions associated
        if ($this->accountEntity->transactionsInvestment()->count() === 0) {
            return new Collection();
        }

        // Get the date of the first last transaction for this account. The last date is the end of this month.
        // The dates need to be taken only for investment transactions,
        // as the investment value is not dependent on standard transactions.
        $firstTransactionDate = Carbon::parse($this->accountEntity->transactionsInvestment()->min('date'));
        $lastTransactionDate = Carbon::now()->endOfMonth();

        // Loop through all months between the first and last transaction, using the first day of the month
        $months = collect($firstTransactionDate->startOfMonth()->monthsUntil($lastTransactionDate))
            ->map(fn ($month) => Carbon::instance($month));

        // Batch-fetch every investment price this run could need, once, up front - the same
        // getLatestPricesBatchExact() precedent getInvestmentValueForecastData() already
        // established - instead of one Investment::find() + price lookup per investment per month.
        $investmentIds = TransactionDetailInvestment::query()
            ->where('account_id', $this->accountEntity->id)
            ->distinct()
            ->pluck('investment_id');
        $investments = Investment::whereIn('id', $investmentIds)->with('currency')->get();

        $priceRequests = new Collection();
        foreach ($months as $month) {
            $endOfMonth = $month->clone()->endOfMonth();
            foreach ($investments as $investment) {
                $priceRequests->push(['investment' => $investment, 'date' => $endOfMonth]);
            }
        }
        $priceMap = $this->investmentService->getLatestPricesBatchExact($priceRequests);

        $results = new Collection();

        foreach ($months as $carbonMonth) {
            $amount = AccountMonthlySummary::calculateInvestmentValueFact(
                $this->accountEntity,
                $carbonMonth,
                $priceMap
            );

            // Here we intentionally store zero values, as it's valid to have a zero value for a month
            // and we don't want to get stuck with a previous non-zero value

            $results->push([
                'date' => $carbonMonth,
                'user_id' => $this->accountEntity->user_id,
                'account_entity_id' => $this->accountEntity->id,
                'transaction_type' => 'investment_value',
                'data_type' => 'fact',
                'amount' => (string) $amount,
            ]);
        }

        return $results;
    }

    /**
     * Get the monthly summary data for investment transactions for the account (accountEntity) provided at class level.
     *
     * This is a relatively complex calculation, as every month's value is calculated based on the previous month.
     * In this case, it involves both previous fact values, and forecast values.
     * The price is still taken from known fact values, but the quantity is calculated based on the forecast values, too
     */
    private function getInvestmentValueForecastData(): Collection
    {
        // Get all active scheduled investment transactions for this account
        $scheduledTransactions = Transaction::with([
            'config',
            'transactionSchedule',
        ])
            ->byType('investment')
            ->isSchedule()
            ->whereHas(
                'transactionSchedule',
                fn ($query) => $query->where('active', true)
            )
            ->whereHasMorph(
                'config',
                TransactionDetailInvestment::class,
                fn ($query) => $query->where('account_id', $this->accountEntity->id)
            )
            // Filter items where the transaction type has a quantity operator
            ->whereIn('transaction_type', TransactionTypeEnum::investmentTypesWithQuantityValues())
            ->get();

        // Get all fact transactions for this account, as it is used as a baseline for the forecast
        $factTransactions = Transaction::with([
            'config',
            'transactionSchedule',
        ])
            ->byType('investment')
            ->where('schedule', false)
            ->whereHasMorph(
                'config',
                TransactionDetailInvestment::class,
                fn ($query) => $query->where('account_id', $this->accountEntity->id)
            )
            // Filter items where the transaction type has a quantity operator
            ->whereIn('transaction_type', TransactionTypeEnum::investmentTypesWithQuantityValues())
            ->get();

        // Get all instances of the schedules, added to a new transactions collection
        $scheduledTransactionInstances = $this->getScheduleInstances($scheduledTransactions, 'next');

        $allTransactionsInstances = $factTransactions->concat($scheduledTransactionInstances);

        // If no investment transactions are found at all, we can return an empty collection
        if ($allTransactionsInstances->isEmpty()) {
            return new Collection();
        }

        // The first date to calculate the forecast is the next month after now
        $firstForecastDate = Carbon::now()->addMonth();

        // We need to forecast until the user's end date
        $lastForecastDate = $this->user->end_date;

        $months = collect($firstForecastDate->startOfMonth()->monthsUntil($lastForecastDate))
            ->map(fn ($month) => Carbon::instance($month)->endOfMonth());

        // Batch-fetch every investment price this run could need, once, up front, instead of
        // one Investment::find() + price lookup per investment per forecast month (FR-9) - this
        // was the dominant cost in a 16-19s job per forecast-performance.md's profiling.
        $investmentIds = $allTransactionsInstances->pluck('config.investment_id')->filter()->unique()->values();
        // Eager-load currency so InvestmentPrice/TransactionDetailInvestment's price-currency
        // resolution (MoneyCast) can reuse these already-loaded Investment instances below,
        // instead of lazy-loading investment.currency per price/transaction row.
        $investments = Investment::whereIn('id', $investmentIds)->with('currency')->get();

        $priceRequests = new Collection();
        foreach ($months as $carbonEndOfMonth) {
            foreach ($investments as $investment) {
                $priceRequests->push(['investment' => $investment, 'date' => $carbonEndOfMonth]);
            }
        }
        // Exact BigDecimal map (not the float-collapsing getLatestPricesBatch()) - this feeds
        // an accumulation below, not a display, so precision must survive past this lookup.
        $priceMap = $this->investmentService->getLatestPricesBatchExact($priceRequests);

        $results = new Collection();
        $currentTransactionCount = 0;
        $quantities = collect();

        foreach ($months as $carbonEndOfMonth) {
            // This loop reproduces the functionality of the calculateInvestmentValueFact method,
            // and that of the getAssociatedInvestmentsAndQuantity method in the Account model,
            // using the already loaded transactions.

            // First, we need to get all the transactions up to the given month
            $transactions = $allTransactionsInstances->where('date', '<=', $carbonEndOfMonth);

            // The quantity does not need to be calculated, if there are no new transactions
            if ($transactions->count() > $currentTransactionCount || $currentTransactionCount === 0) {
                // Then, we need to group the transactions by investment_id of the config
                $groupedTransactions = $transactions->groupBy('config.investment_id');

                // For all groups, let's calculate the cummulated quantity up to the end of the month.
                // $transaction->config->quantity is already a BigDecimal (DecimalCast) - no
                // need to unwrap it, since this feeds an accumulation, not a display.
                $quantities = $groupedTransactions->map(
                    fn ($group) => $this->sumBigDecimal(
                        $group,
                        fn ($transaction) => $transaction->config?->quantity
                            ?->multipliedBy($transaction->transaction_type->quantityMultiplier())
                    )
                );
            }

            $amount = $this->sumBigDecimal(
                $quantities,
                function (BigDecimal $quantity, $investmentId) use ($carbonEndOfMonth, $priceMap) {
                    // Get the latest known price up to this date, from the pre-fetched map
                    $latestPrice = $priceMap[$this->investmentService->priceBatchKey((int) $investmentId, $carbonEndOfMonth)] ?? null;

                    if ($latestPrice === null) {
                        return null;
                    }

                    return $quantity->multipliedBy($latestPrice);
                }
            );

            // Here we intentionally store zero values, as it's valid to have a zero value for a month
            // and we don't want to get stuck with a previous non-zero value
            $results->push([
                'date' => $carbonEndOfMonth->clone()->startOfMonth(),
                'user_id' => $this->accountEntity->user_id,
                'account_entity_id' => $this->accountEntity->id,
                'transaction_type' => 'investment_value',
                'data_type' => 'forecast',
                'amount' => (string) $amount,
            ]);

            // Store the number of currently processed transactions
            $currentTransactionCount = $transactions->count();
        }

        return $results;
    }

    /**
     * Get the monthly summary data for the budget of the account (accountEntity) provided at class level.
     * Optionally, we use the transactions of the user, if the account is not provided.
     *
     * This function uses a custom calculation. All active budget transactions are retrieved,
     * all instances are calculated, and then the sum of the cashflow_value is calculated for each month,
     * starting from the current month.
     */
    /**
     * Get the monthly summary data for the budget of the account (accountEntity) provided at
     * class level, or the account-agnostic bucket if none is provided (FR-3).
     *
     * Reads only active, standalone Budget rows attributed to this exact bucket (a row's own
     * account_id must match $this->accountEntity, or be null when no account is provided) -
     * mirroring how getAccountBalanceForecastData() filters on transactionSchedule.active.
     */
    private function getAccountBalanceBudgetData(): Collection
    {
        // $budget->amount (MoneyCast) resolves its currency via Budget::currency() on every
        // access below - eager-load what that needs (the account-scoped path, and the user
        // relation for the account-agnostic/base-currency fallback) instead of lazy-loading it
        // per row.
        $budgets = Budget::with(['account.config.currency', 'user'])
            ->where('user_id', $this->user->id)
            ->where('active', true)
            ->when(
                $this->accountEntity,
                fn ($query) => $query->where('account_id', $this->accountEntity->id),
                fn ($query) => $query->whereNull('account_id')
            )
            ->get();

        // If no budgets are found, we can return an empty collection
        if ($budgets->isEmpty()) {
            return new Collection();
        }

        $inflationCalculator = new InflationCalculator();
        // BudgetService/RecurrenceRuleService type-hint Illuminate\Support\Carbon (not this
        // file's Carbon\Carbon import) - Carbon::now() here would be the wrong, incompatible type.
        $horizonStart = \Illuminate\Support\Carbon::now()->startOfMonth();
        $horizonEnd = $this->user->end_date;

        // Sum every budget's inflation-adjusted, signed (withdrawal/deposit) contribution per
        // month, so multiple budgets landing in the same month/category are combined rather than
        // overwriting each other.
        $amountsByMonth = [];

        foreach ($budgets as $budget) {
            $occurrences = $this->budgetService->projectOccurrences($budget, $horizonStart, $horizonEnd);

            foreach ($occurrences as $occurrenceDate) {
                $month = $occurrenceDate->format('Y-m-01');
                // Same "apply to a unit multiplier" pattern as Transaction::scheduleInstances()'s
                // inflationMultiplier, so the exact BigDecimal amount below is only ever multiplied
                // (never added to/subtracted from a float).
                $multiplier = $inflationCalculator->applyAnnualRate(
                    1.0,
                    $budget->inflation,
                    $budget->start_date,
                    $occurrenceDate,
                );
                $amount = $budget->amount->getAmount()
                    ->multipliedBy($multiplier)
                    ->multipliedBy($budget->transaction_type->amountMultiplier());

                $amountsByMonth[$month] = ($amountsByMonth[$month] ?? BigDecimal::zero())->plus($amount);
            }
        }

        $results = new Collection();

        foreach ($amountsByMonth as $month => $amount) {
            // Don't store zero values
            if ($amount->isZero()) {
                continue;
            }

            $results->push([
                'date' => Carbon::createFromFormat('Y-m-d', $month),
                'user_id' => $this->user->id,
                'account_entity_id' => $this->accountEntity?->id,
                'transaction_type' => 'account_balance',
                'data_type' => 'budget',
                'amount' => (string) $amount,
            ]);
        }

        return $results;
    }
}
