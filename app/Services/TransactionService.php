<?php

namespace App\Services;

use App\Jobs\CalculateAccountMonthlySummary;
use App\Models\AccountEntity;
use App\Models\Transaction;

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

        $operator = $transaction->transactionType->amount_operator;
        if ($operator) {
            return ($operator === 'minus' ? -1 : 1)
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
     * based on the creation or change of on specific transaction.
     * The function will trigger the relevant job or jobs to recalculate the summaries.
     *
     * @param Transaction $transaction
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
     *
     * @param Transaction $transaction
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

                // As this is a relatively quick job, we can use dispatch_sync
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

                // As this is a relatively quick job, we can use dispatch_sync
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
                dispatch($job);
            }

            if ($accountTo->isAccount()) {
                $job = new CalculateAccountMonthlySummary(
                    $transaction->user,
                    'account_balance-forecast',
                    $accountTo
                );
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
            dispatch($job);
        } elseif ($accountTo?->isAccount()) {
            $job = new CalculateAccountMonthlySummary(
                $transaction->user,
                'account_balance-budget',
                $accountTo
            );
            dispatch($job);
        } else {
            // No account to assign the budget to
            $job = new CalculateAccountMonthlySummary(
                $transaction->user,
                'account_balance-budget'
            );
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
}
