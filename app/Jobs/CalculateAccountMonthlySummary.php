<?php

namespace App\Jobs;

use App\Http\Traits\ScheduleTrait;
use App\Models\AccountEntity;
use App\Models\AccountMonthlySummary;
use App\Models\Investment;
use App\Models\Transaction;
use App\Models\TransactionDetailInvestment;
use App\Models\TransactionDetailStandard;
use App\Models\TransactionType;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
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
    private AccountEntity|null $accountEntity;
    private string $task;
    private Carbon|null $dateFrom;
    private Carbon|null $dateTo;

    public int $timeout = 240;

    /**
     * Create a new job instance.
     */
    public function __construct(
        User $user,
        string $task,
        AccountEntity $accountEntity = null,
        Carbon $dateFrom = null,
        Carbon $dateTo = null
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
    public function handle(): void
    {
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
     * The function loops through all months between the first and last transaction,
     * and also prepends the opening balance as the first available month.
     */
    private function getAccountBalanceFactData(): Collection
    {
        // Get the dates of the first and last transaction for this account
        $firstTransactionDate = $this->dateFrom ?? Carbon::parse($this->accountEntity->allTransactionDates()->min('date'));
        $lastTransactionDate = Carbon::parse($this->accountEntity->allTransactionDates()->max('date'));

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
            if ($amount === 0 || $amount === 0.0) {
                continue;
            }

            // Push new data to the results collection, which represents an AccountMonthlySummary model
            $results->push([
                'date' => $carbonMonth,
                'user_id' => $this->accountEntity->user_id,
                'account_entity_id' => $this->accountEntity->id,
                'transaction_type' => 'account_balance',
                'data_type' => 'fact',
                'amount' => $amount,
            ]);
        }

        // Add the opening balance before the first known month
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
            'amount' => $this->accountEntity->config->opening_balance,
        ]);

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
            ->byScheduleType('schedule')
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
            ->byScheduleType('schedule')
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

        // If no scheduled transactions are found, we can return an empty collection
        if ($scheduledStandardTransactions->isEmpty() && $scheduledInvestmentTransactions->isEmpty()) {
            return new Collection();
        }

        // Get all instances, added to a new transactions collection
        $scheduledStandardTransactionInstances = $this->getScheduleInstances($scheduledStandardTransactions, 'next');
        $scheduledInvestmentTransactionInstances = $this->getScheduleInstances($scheduledInvestmentTransactions, 'next');

        // Convert the transaction dates to 'Y-m' format and group by the formatted date
        $scheduledStandardTransactionInstances = $scheduledStandardTransactionInstances
            ->groupBy(fn ($transaction) => Carbon::parse($transaction->date)->format('Y-m'));
        $scheduledInvestmentTransactionInstances = $scheduledInvestmentTransactionInstances
            ->groupBy(fn ($transaction) => Carbon::parse($transaction->date)->format('Y-m'));

        $results = new Collection();

        // Loop through the grouped transactions
        foreach ($scheduledStandardTransactionInstances as $month => $standardTransactions) {
            $investmentTransactions = $scheduledInvestmentTransactionInstances[$month] ?? collect();

            // Split the transactions into from and to transactions
            [$transactionsFrom, $transactionsTo] = $standardTransactions->partition(
                fn ($transaction) =>
                    $transaction->config->account_from_id === $this->accountEntity->id
            );

            $amountFrom = $transactionsFrom->sum('config.amount_from');
            $amountTo = $transactionsTo->sum('config.amount_to');
            $amountInvestment = $investmentTransactions->sum('cashflow_value');

            $amount = $amountInvestment + $amountTo - $amountFrom;

            // Don't store zero values
            if ($amount === 0 || $amount === 0.0) {
                continue;
            }

            $results->push([
                'date' => Carbon::createFromFormat('Y-m', $month)->startOfMonth(),
                'user_id' => $this->accountEntity->user_id,
                'account_entity_id' => $this->accountEntity->id,
                'transaction_type' => 'account_balance',
                'data_type' => 'forecast',
                'amount' => $amount,
            ]);
        }

        return $results;
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
        // The dates need to be taken only for investment transactions, as the investment value is not dependent on standard transactions.
        $firstTransactionDate = Carbon::parse($this->accountEntity->transactionsInvestment()->min('date'));
        $lastTransactionDate = Carbon::now()->endOfMonth();

        // Loop through all months between the first and last transaction, using the first day of the month
        $period = $firstTransactionDate->startOfMonth()->monthsUntil($lastTransactionDate);

        $results = new Collection();

        foreach ($period as $month) {
            // Create a Carbon instance of the month
            $carbonMonth = Carbon::instance($month);

            $amount = AccountMonthlySummary::calculateInvestmentValueFact(
                $this->accountEntity,
                $carbonMonth
            );

            // Here we intentionally store zero values, as it's valid to have a zero value for a month
            // and we don't want to get stuck with a previous non-zero value

            $results->push([
                'date' => $carbonMonth,
                'user_id' => $this->accountEntity->user_id,
                'account_entity_id' => $this->accountEntity->id,
                'transaction_type' => 'investment_value',
                'data_type' => 'fact',
                'amount' => $amount,
            ]);
        }

        return $results;
    }

    /**
     * Get the monthly summary data for investment transactions for the account (accountEntity) provided at class level.
     *
     * This is a relatively complex calculation, as every month's value is calculated based on the previous month's value.
     * In this case, it involves both previous fact values, and forecast values.
     * The price is still taken from known fact values, but the quantity is calculated based on the forecast values, too.
     *
     * @return Collection
     */
    private function getInvestmentValueForecastData(): Collection
    {
        // Get all active scheduled investment transactions for this account
        $scheduledTransactions = Transaction::with([
            'config',
            'transactionType',
            'transactionSchedule',
        ])
            ->where('config_type', 'investment')
            ->where('schedule', true)
            ->whereHas(
                'transactionSchedule',
                fn ($query) => $query->where('active', true)
            )
            ->whereHasMorph(
                'config',
                TransactionDetailInvestment::class,
                fn ($query) => $query->where('account_id', $this->accountEntity->id)
            )
            // Additionally, exclude items where the transactiontype is not associated with a quantity operator
            ->whereHas(
                'transactionType',
                fn ($query) => $query->whereNotNull('quantity_operator')
            )
            ->get();

        // Get all fact transactions for this account, as it is used as a baseline for the forecast
        $factTransactions = Transaction::with([
            'config',
            'transactionType',
            'transactionSchedule',
        ])
            ->where('config_type', 'investment')
            ->where('schedule', false)
            // Investment transactions should never be budget transactions
            ->where('budget', false)
            ->whereHasMorph(
                'config',
                TransactionDetailInvestment::class,
                fn ($query) => $query->where('account_id', $this->accountEntity->id)
            )
            // Additionally, exclude items where the transactiontype is not associated with a quantity operator
            ->whereHas(
                'transactionType',
                fn ($query) => $query->whereNotNull('quantity_operator')
            )
            ->get();

        // Get all instances of the schedules, added to a new transactions collection
        $scheduledTransactionInstances = $this->getScheduleInstances($scheduledTransactions, 'next');
        $allTransactionsInstances = $factTransactions->merge($scheduledTransactionInstances);

        // If no investment transactions are found at all, we can return an empty collection
        if ($allTransactionsInstances->isEmpty()) {
            return new Collection();
        }

        // We need to get the first and last transaction dates for the later loop,
        // but starting only after the last known fact date
        $firstTransactionDate = Carbon::parse($allTransactionsInstances->min('date'));

        // The first date to calculate the forecast is the next month after the last known fact date
        // or the next month after now, if there are no fact transactions
        $firstForecastDate = Carbon::now()->addMonth();

        // We can't forecast until the last known date, or the user's end date
        $lastForecastDate = max(
            Carbon::parse($allTransactionsInstances->max('date')),
            $this->user->end_date
        );

        $period = $firstForecastDate->startOfMonth()->monthsUntil($lastForecastDate);

        $results = new Collection();
        $currentTransactionCount = 0;

        foreach ($period as $month) {
            // Create a Carbon instance of the month
            $carbonStartofMonth = Carbon::instance($month)->startOfMonth();
            $carbonEndofMonth = $carbonStartofMonth->clone()->endOfMonth();

            // This loop reproduces the functionality of the calculateInvestmentValueFact method,
            // and that of the getAssociatedInvestmentsAndQuantity method in the Account model,
            // using the already loaded transactions.

            // First, we need to get all the transactions up to the given month
            $transactions = $allTransactionsInstances->whereBetween(
                'date',
                [$firstTransactionDate, $carbonEndofMonth]
            );

            // The quantity does not need to be calculated, if there are no new transactions
            if ($transactions->count() > $currentTransactionCount || $currentTransactionCount === 0) {
                // Then, we need to group the transactions by investment_id of the config
                $groupedTransactions = $transactions->groupBy('config.investment_id');

                // For all groups, let's calculate the cummulated quantity up to the end of the month
                $quantities = $groupedTransactions->map(
                    fn ($group) => $group->sum(
                        fn ($transaction) => $transaction->transactionType->quantity_operator === 'plus'
                            ? $transaction->config->quantity
                            : -$transaction->config->quantity
                    )
                );
            }

            $amount = $quantities->map(function ($quantity, $investmentId) use ($carbonEndofMonth) {
                // Get the latest known price up to this date
                $latestPrice = Investment::find($investmentId)
                    ->getLatestPrice('combined', $carbonEndofMonth);

                return $quantity * $latestPrice;
            })
                ->sum();

            // Here we intentionally store zero values, as it's valid to have a zero value for a month
            // and we don't want to get stuck with a previous non-zero value
            $results->push([
                'date' => $carbonStartofMonth,
                'user_id' => $this->accountEntity->user_id,
                'account_entity_id' => $this->accountEntity->id,
                'transaction_type' => 'investment_value',
                'data_type' => 'forecast',
                'amount' => $amount,
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
     * This function uses a custom calculation. All active budget transactions are retrieved, all instances are calculated,
     * and then the sum of the cashflow_value is calculated for each month, starting from the current month.
     */
    private function getAccountBalanceBudgetData(): Collection
    {
        // Get all budget only transactions for this account, or for the user
        $budgetTransactions = Transaction::with([
            'config',
            'transactionSchedule',
        ])
            ->where('user_id', $this->user->id)
            ->byType('standard')
            // Budgets with schedules are handled by the schedule forecast
            ->byScheduleType('budget_only')
            // The schedule must be still active
            ->whereHas(
                'transactionSchedule',
                fn ($query) => $query->where('active', true)
            )
            ->where(
                fn ($query) => $query
                    ->when(
                        $this->accountEntity,
                        fn ($query) => $query->whereHasMorph(
                            'config',
                            TransactionDetailStandard::class,
                            fn ($query) => $query
                                ->where('account_from_id', $this->accountEntity->id)
                                ->orWhere('account_to_id', $this->accountEntity->id)
                        ),
                        // If no account is specified, then we need to take the transactions of the user without an account
                        // This needs to be checked separately to withdrawal and deposit transactions, as the proper acocunt needs to be null
                        // (Not expected, but the payee can be set as the other account.)
                        fn ($query) => $query->where(
                            // Withdrawals without an account_from_id
                            fn ($query) => $query
                                ->whereHas('transactionType', fn ($query) => $query->where('name', 'withdrawal'))
                                ->whereHasMorph(
                                    'config',
                                    TransactionDetailStandard::class,
                                    fn ($query) => $query
                                        ->whereNull('account_from_id')
                                )
                        )
                            // Deposits without an account_to_id
                            ->orWhere(
                                fn ($query) => $query
                                    ->whereHas('transactionType', fn ($query) => $query->where('name', 'deposit'))
                                    ->orWhereHasMorph(
                                        'config',
                                        TransactionDetailStandard::class,
                                        fn ($query) => $query
                                            ->whereNull('account_to_id')
                                    )
                            )
                    )
            )
            ->get();

        // If no budget transactions are found, we can return an empty collection
        if ($budgetTransactions->isEmpty()) {
            return new Collection();
        }

        // Get all instances, added to a new transactions collection, only from the current month
        $budgetTransactionInstances = $this->getScheduleInstances($budgetTransactions, 'custom', Carbon::now()->startOfMonth());

        // Convert the transaction dates to 'Y-m' format and group by the formatted date
        $budgetTransactionInstances = $budgetTransactionInstances
            ->groupBy(fn ($transaction) => Carbon::parse($transaction->date)->format('Y-m'));

        $results = new Collection();

        // Loop through the grouped transactions
        foreach ($budgetTransactionInstances as $month => $transactions) {
            $amount = $transactions->sum('cashflow_value');

            // Don't store zero values
            if ($amount === 0 || $amount === 0.0) {
                continue;
            }

            $results->push([
                'date' => Carbon::createFromFormat('Y-m', $month)->startOfMonth(),
                'user_id' => $this->user->id,
                'account_entity_id' => $this->accountEntity?->id,
                'transaction_type' => 'account_balance',
                'data_type' => 'budget',
                'amount' => $amount,
            ]);
        }

        return $results;
    }
}
