<?php

namespace App\Services;

use App\Jobs\CalculateAccountMonthlySummary;
use App\Models\AccountEntity;
use App\Models\Transaction;
use App\Models\TransactionDetailInvestment;
use App\Models\TransactionDetailStandard;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class TransactionService
{
    /**
     * Create a new standalone transaction from a scheduled transaction
     * - clone all the related models
     * - use the next scheduled date as the transaction date
     * - remove the schedule and budget flags from the transaction
     * - adjust the next date of the original transaction
     */
    public function enterScheduleInstance(Transaction $transaction): void
    {
        // Clone the transaction using cloner
        /** @var Transaction $newTransaction */
        $newTransaction = $transaction->duplicate();

        // Set the date to the next scheduled date
        $newTransaction->date = $transaction->transactionSchedule->next_date;

        // Remove the schedule and budget flags
        $newTransaction->schedule = false;
        $newTransaction->budget = false;

        // Save the new transaction
        $newTransaction->save();

        // Adjust the next date of the original transaction
        $transaction->transactionSchedule->skipNextInstance();
    }

    /**
     * Get the currency ID associated with the transaction, based on its config
     */
    public function getTransactionCurrencyId(Transaction $transaction): ?int
    {
        if ($transaction->isStandard()) {
            return $this->getStandardConfigCurrencyId($transaction);
        }
        if ($transaction->isInvestment()) {
            return $this->getInvestmentConfigCurrencyId($transaction);
        }
        return null;
    }

    private function getStandardConfigCurrencyId(Transaction $transaction): ?int
    {
        $transaction->loadMissing([
            'config',
            'config.accountFrom',
            'config.accountTo',
            'config.accountFrom.config',
            'config.accountTo.config',
            'transactionType'
        ]);

        if ($transaction->transactionType->name === 'deposit') {
            return $transaction->config->accountTo?->config->currency_id;
        }
        if ($transaction->transactionType->name === 'withdrawal') {
            return $transaction->config->accountFrom?->config->currency_id;
        }

        return null;
    }

    private function getInvestmentConfigCurrencyId(Transaction $transaction): ?int
    {
        $transaction->loadMissing([
            'config',
            'config.account',
            'config.account.config',
        ]);

        return $transaction->config->account->config->currency_id;
    }

    /**
     * Get the monetary value associated with the cash flow of the transaction
     */
    public function getTransactionCashFlow(Transaction $transaction): ?float
    {
        if ($transaction->isStandard()) {
            return $this->getStandardConfigCashFlow($transaction);
        }
        if ($transaction->isInvestment()) {
            return $this->getInvestmentConfigCashFlow($transaction);
        }
        return null;
    }

    private function getStandardConfigCashFlow(Transaction $transaction): ?float
    {
        $transaction->loadMissing([
            'config',
            'transactionType'
        ]);

        if ($transaction->transactionType->name === 'deposit') {
            return $transaction->config->amount_from;
        }
        if ($transaction->transactionType->name === 'withdrawal') {
            return $transaction->config->amount_from * -1;
        }

        return null;
    }

    private function getInvestmentConfigCashFlow(Transaction $transaction): ?float
    {
        $transaction->loadMissing([
            'config',
            'transactionType'
        ]);

        if ($transaction->transactionType->amount_multiplier !== null) {
            return $transaction->transactionType->amount_multiplier
                * $transaction->config->price
                * $transaction->config->quantity

                + $transaction->config->dividend
                - $transaction->config->tax
                - $transaction->config->commission;
        }

        return null;
    }

    /**
     * This function can be used to initiate the recalculation of the monthly summaries,
     * based on the creation or any change of a transaction.
     * The function will trigger the relevant job or jobs to recalculate the summaries.
     */
    public function recalculateMonthlySummaries(Transaction $transaction): void
    {
        if ($transaction->isStandard()) {
            $this->recalculateSummaryStandard($transaction);
        } elseif ($transaction->isInvestment()) {
            $this->recalculateSummaryInvestment($transaction);
        }
    }

    /**
     * This function will initiate the recalculation of the monthly summaries for standard transactions,
     * based on the properties of the given transaction. (transaction type, schedule, budget)
     */
    private function recalculateSummaryStandard(Transaction $transaction): void
    {
        $transaction->loadMissing([
            'config',
            'config.accountFrom',
            'config.accountTo',
        ]);

        /** @var AccountEntity $accountFrom */
        $accountFrom = $transaction->config->accountFrom;

        /** @var AccountEntity $accountTo */
        $accountTo = $transaction->config->accountTo;

        if (!$transaction->schedule && !$transaction->budget) {
            // This is a simple transaction with no schedule or budget attached
            // We need to recalculate only the given month for one or both accounts
            if ($accountFrom->isAccount()) {
                $job = new CalculateAccountMonthlySummary(
                    $transaction->user,
                    'account_balance-fact',
                    $accountFrom,
                    $transaction->date->clone()->startOfMonth(),
                    $transaction->date->clone()->endOfMonth()
                );

                // As this is a relatively quick job, affecting only one month and no schedules, we can use dispatch_sync
                dispatch_sync($job);
            }

            if ($accountTo->isAccount()) {
                $job = new CalculateAccountMonthlySummary(
                    $transaction->user,
                    'account_balance-fact',
                    $accountTo,
                    $transaction->date->clone()->startOfMonth(),
                    $transaction->date->clone()->endOfMonth()
                );

                // As this is a relatively quick job, affecting only one month and no schedules, we can use dispatch_sync
                dispatch_sync($job);
            }

            return;
        }

        if ($transaction->schedule) {
            // This is a scheduled transaction, optionally with a budget attached
            // We need to recalculate the entire forecast for one or both accounts
            if ($accountFrom->isAccount()) {
                $job = new CalculateAccountMonthlySummary(
                    $transaction->user,
                    'account_balance-forecast',
                    $accountFrom
                );

                // We don't know how long the schedule will be, so we need to dispatch the job to the queue
                dispatch($job);
            }

            if ($accountTo->isAccount()) {
                $job = new CalculateAccountMonthlySummary(
                    $transaction->user,
                    'account_balance-forecast',
                    $accountTo
                );

                // We don't know how long the schedule will be, so we need to dispatch the job to the queue
                dispatch($job);
            }

            return;
        }

        // This is a budget-only transaction
        // We need to recalculate the entire budget for one of the accounts or none
        // As a budget cannot be transfer, we'll never have both accounts
        if ($accountFrom?->isAccount()) {
            $job = new CalculateAccountMonthlySummary(
                $transaction->user,
                'account_balance-budget',
                $accountFrom
            );

            // We don't know how long the schedule will be, so we need to dispatch the job to the queue
            dispatch($job);
        } elseif ($accountTo?->isAccount()) {
            $job = new CalculateAccountMonthlySummary(
                $transaction->user,
                'account_balance-budget',
                $accountTo
            );

            // We don't know how long the schedule will be, so we need to dispatch the job to the queue
            dispatch($job);
        } else {
            // No account to assign the budget to
            $job = new CalculateAccountMonthlySummary(
                $transaction->user,
                'account_balance-budget'
            );

            // We don't know how long the schedule will be, so we need to dispatch the job to the queue
            dispatch($job);
        }
    }

    private function recalculateSummaryInvestment(Transaction $transaction): void
    {
        $transaction->loadMissing([
            'config',
            'config.account',
        ]);

        /** @var AccountEntity $account */
        $account = $transaction->config->account;

        if (!$transaction->schedule) {
            // This is a simple transaction with no schedule attached
            // As the investment summaries store the cummulated value, we need to recalculate all months
            // Theoretically, an investment transaction always has an account
            $job = new CalculateAccountMonthlySummary(
                $transaction->user,
                'investment_value-fact',
                $account
            );
            dispatch($job);
        }

        // We always need to recalculate the entire forecast for the account, as the baseline changes
        $job = new CalculateAccountMonthlySummary(
            $transaction->user,
            'investment_value-forecast',
            $account
        );
        dispatch($job);
    }

    /**
     * Get standard transactions for an account (one-time AND scheduled)
     */
    public function getAccountStandardTransactions(AccountEntity $account, int $userId): Collection
    {
        return Transaction::where(function ($query) {
            $query->where('schedule', 1)
                ->orWhere(function ($query) {
                    $query->byScheduleType('none');
                });
        })
            ->where('user_id', $userId)
            ->whereHasMorph(
                'config',
                [TransactionDetailStandard::class],
                function (Builder $query) use ($account) {
                    $query->where('account_from_id', $account->id);
                    $query->orWhere('account_to_id', $account->id);
                }
            )
            ->with([
                'config',
                'transactionType',
                'transactionItems',
                'transactionItems.category',
                'transactionItems.tags',
            ])
            ->get();
    }

    /**
     * Get investment transactions for an account (one-time AND scheduled)
     */
    public function getAccountInvestmentTransactions(AccountEntity $account, int $userId): Collection
    {
        return Transaction::where(function ($query) {
            $query->where('schedule', 1)
                ->orWhere(function ($query) {
                    $query->byScheduleType('none');
                });
        })
            ->where('user_id', $userId)
            ->whereHasMorph(
                'config',
                [TransactionDetailInvestment::class],
                function (Builder $query) use ($account) {
                    $query->where('account_id', $account->id);
                }
            )
            ->with([
                'config',
                'config.investment',
                'transactionType',
            ])
            ->get();
    }

    /**
     * Enrich a transaction with additional attributes for display
     */
    public function enrichTransactionForDisplay(
        Transaction $transaction,
        AccountEntity $currentAccount,
        array $allAccounts
    ): Transaction {
        // Set transaction group
        if ($transaction->schedule) {
            $transaction->load(['transactionSchedule']);
            $transaction->transactionGroup = 'schedule';
        } else {
            $transaction->transactionGroup = 'history';
        }

        // Enrich based on transaction type
        if ($transaction->isStandard()) {
            $this->enrichStandardTransaction($transaction, $currentAccount, $allAccounts);
        } elseif ($transaction->isInvestment()) {
            $this->enrichInvestmentTransaction($transaction, $currentAccount, $allAccounts);
        }

        return $transaction;
    }

    /**
     * Enrich a standard transaction with display attributes
     */
    private function enrichStandardTransaction(
        Transaction $transaction,
        AccountEntity $currentAccount,
        array $allAccounts
    ): void {
        $transaction->transactionOperator = $transaction->transactionType->amount_multiplier
            ?? ($transaction->config->account_from_id === $currentAccount->id ? -1 : 1);
        $transaction->account_from_name = $allAccounts[$transaction->config->account_from_id];
        $transaction->account_to_name = $allAccounts[$transaction->config->account_to_id];
        $transaction->amount_from = $transaction->config->amount_from;
        $transaction->amount_to = $transaction->config->amount_to;
        $transaction->tags = $transaction->tags()->values();
        $transaction->categories = $transaction->categories()->values();
    }

    /**
     * Enrich an investment transaction with display attributes
     */
    private function enrichInvestmentTransaction(
        Transaction $transaction,
        AccountEntity $currentAccount,
        array $allAccounts
    ): void {
        $amount = $transaction->cashflow_value ?? 0;

        $transaction->transactionOperator = $transaction->transactionType->amount_multiplier;
        $transaction->account_from_name = $allAccounts[$transaction->config->account_id];
        $transaction->account_to_name = $transaction->config->investment->name;
        $transaction->amount_from = ($amount < 0 ? -$amount : null);
        $transaction->amount_to = ($amount > 0 ? $amount : null);
        $transaction->tags = [];
        $transaction->categories = [];
        $transaction->quantity = $transaction->config->quantity;
        $transaction->price = $transaction->config->price;
        $transaction->currency = $currentAccount->config->currency;
    }
}
