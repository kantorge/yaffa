<?php

namespace App\Services;

use App\Enums\TransactionType as TransactionTypeEnum;
use App\Jobs\CalculateAccountMonthlySummary;
use App\Models\Account;
use App\Models\AccountEntity;
use App\Models\Transaction;
use App\Models\TransactionDetailInvestment;
use App\Models\TransactionDetailStandard;
use Brick\Math\RoundingMode;
use Brick\Money\Money;
use Illuminate\Support\Facades\Log;

class TransactionService
{
    /**
     * Create a new standalone transaction from a scheduled transaction
     * - clone all the related models
     * - use the next scheduled date as the transaction date
     * - remove the schedule flag from the transaction
     * - adjust the next date of the original transaction
     */
    public function enterScheduleInstance(Transaction $transaction): void
    {
        // Ensure the schedule is loaded
        $transaction->loadMissing('transactionSchedule');

        // Clone the transaction using cloner
        /** @var Transaction $newTransaction */
        $newTransaction = $transaction->duplicate();

        // Set the date to the next scheduled date
        $newTransaction->date = $transaction->transactionSchedule->next_date;

        // Remove the schedule flag
        $newTransaction->schedule = false;

        // Save the new transaction
        $newTransaction->save();

        // Merge transaction items if the user's setting is enabled
        (new TransactionItemMergeService())->mergeIfEnabled($newTransaction);

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
        ]);

        /** @var TransactionDetailStandard|null $config */
        $config = $this->getStandardConfig($transaction);

        if ($config === null) {
            return null;
        }

        if ($transaction->transaction_type === TransactionTypeEnum::DEPOSIT) {
            return $this->getAccountCurrencyIdFromEntity($config->accountTo);
        }
        if ($transaction->transaction_type === TransactionTypeEnum::WITHDRAWAL) {
            return $this->getAccountCurrencyIdFromEntity($config->accountFrom);
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

        /** @var TransactionDetailInvestment|null $config */
        $config = $this->getInvestmentConfig($transaction);

        if ($config === null) {
            return null;
        }

        return $this->getAccountCurrencyIdFromEntity($config->account);
    }

    /**
     * Get the monetary value associated with the cash flow of the transaction, computed
     * exactly end-to-end (Money) all the way through to Transaction::cashflow_value's
     * write path - see FR-7.
     */
    public function getTransactionCashFlow(Transaction $transaction): ?Money
    {
        if ($transaction->isStandard()) {
            return $this->getStandardConfigCashFlow($transaction);
        }
        if ($transaction->isInvestment()) {
            return $this->getInvestmentConfigCashFlow($transaction);
        }
        return null;
    }

    private function getStandardConfigCashFlow(Transaction $transaction): ?Money
    {
        $transaction->loadMissing([
            'config',
        ]);

        /** @var TransactionDetailStandard|null $config */
        $config = $this->getStandardConfig($transaction);

        if ($config === null) {
            return null;
        }

        if ($transaction->transaction_type === TransactionTypeEnum::DEPOSIT) {
            return $config->amount_from;
        }
        if ($transaction->transaction_type === TransactionTypeEnum::WITHDRAWAL) {
            return $config->amount_from->negated();
        }

        return null;
    }

    private function getInvestmentConfigCashFlow(Transaction $transaction): ?Money
    {
        $transaction->loadMissing([
            'config',
        ]);

        /** @var TransactionDetailInvestment|null $config */
        $config = $this->getInvestmentConfig($transaction);

        if ($config === null) {
            return null;
        }

        $multiplier = $transaction->transaction_type->amountMultiplier();

        if ($multiplier === null) {
            return null;
        }

        // Build the cash flow from whichever terms are present, exactly (Money/BigDecimal),
        // treating a missing (nullable) field as no contribution - same as the previous
        // float expression, where a null operand was implicitly treated as 0.
        $terms = [];

        if ($config->price !== null && $config->quantity !== null) {
            $terms[] = $config->price->multipliedBy($config->quantity, RoundingMode::HalfUp)->multipliedBy($multiplier);
        }
        if ($config->dividend !== null) {
            $terms[] = $config->dividend;
        }
        if ($config->tax !== null) {
            $terms[] = $config->tax->negated();
        }
        if ($config->commission !== null) {
            $terms[] = $config->commission->negated();
        }

        if ($terms === []) {
            return null;
        }

        // Legacy data: an investment transaction whose account currency no longer matches
        // its investment's currency (one of the two was changed after this transaction was
        // recorded - TransactionRequest::accountInvestmentCurrencyMatchRule() blocks this
        // for new transactions, but can't fix rows that already existed). Money::plus()
        // would throw MoneyMismatchException combining them - treat this the same as
        // "cannot compute" (same as a missing operand above) instead of letting the
        // exception escape.
        $firstCurrency = $terms[0]->getCurrency();
        foreach ($terms as $term) {
            if (! $term->getCurrency()->is($firstCurrency)) {
                Log::warning('Investment transaction cash flow spans mismatched currencies (legacy data)', [
                    'transaction_id' => $transaction->id,
                ]);

                return null;
            }
        }

        return array_reduce(
            array_slice($terms, 1),
            fn (Money $carry, Money $term) => $carry->plus($term),
            $terms[0]
        );
    }

    /**
     * This function is used to initiate the recalculation of the monthly summaries,
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
     * based on the properties of the given transaction. (transaction type, schedule)
     */
    private function recalculateSummaryStandard(Transaction $transaction): void
    {
        $transaction->loadMissing([
            'config',
            'config.accountFrom',
            'config.accountTo',
        ]);

        /** @var TransactionDetailStandard|null $config */
        $config = $this->getStandardConfig($transaction);

        if ($config === null) {
            return;
        }

        /** @var AccountEntity|null $accountFrom */
        $accountFrom = $config->accountFrom;

        /** @var AccountEntity|null $accountTo */
        $accountTo = $config->accountTo;

        if (!$transaction->schedule) {
            // This is a simple transaction with no schedule attached
            // We need to recalculate only the given month for one or both accounts
            if ($accountFrom?->isAccount()) {
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

            if ($accountTo?->isAccount()) {
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

        // This is a scheduled transaction
        // We need to recalculate the entire forecast for one or both accounts
        if ($accountFrom?->isAccount()) {
            $job = new CalculateAccountMonthlySummary(
                $transaction->user,
                'account_balance-forecast',
                $accountFrom
            );

            // We don't know how long the schedule will be, so we need to dispatch the job to the queue
            dispatch($job);
        }

        if ($accountTo?->isAccount()) {
            $job = new CalculateAccountMonthlySummary(
                $transaction->user,
                'account_balance-forecast',
                $accountTo
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

        /** @var TransactionDetailInvestment|null $config */
        $config = $this->getInvestmentConfig($transaction);

        if ($config === null) {
            return;
        }

        /** @var AccountEntity $account */
        $account = $config->account;

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

            // As the change probably affects the balance of the account, we also need to recalculate the standard summaries for the account
            $job = new CalculateAccountMonthlySummary(
                $transaction->user,
                'account_balance-fact',
                $account,
                $transaction->date->clone()->startOfMonth(),
                $transaction->date->clone()->endOfMonth()
            );
            dispatch($job);
        }

        if ($transaction->schedule) {
            // This is a scheduled transaction, we need to recalculate the entire forecast for the account, as the baseline changes
            $job = new CalculateAccountMonthlySummary(
                $transaction->user,
                'account_balance-forecast',
                $account
            );

            // We don't know how long the schedule will be, so we need to dispatch the job to the queue
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

    private function getStandardConfig(Transaction $transaction): ?TransactionDetailStandard
    {
        $config = $transaction->config;

        if (!$config instanceof TransactionDetailStandard) {
            return null;
        }

        return $config;
    }

    private function getInvestmentConfig(Transaction $transaction): ?TransactionDetailInvestment
    {
        $config = $transaction->config;

        if (!$config instanceof TransactionDetailInvestment) {
            return null;
        }

        return $config;
    }

    private function getAccountCurrencyIdFromEntity(?AccountEntity $accountEntity): ?int
    {
        if ($accountEntity === null || !$accountEntity->isAccount()) {
            return null;
        }

        $accountConfig = $accountEntity->config;

        if (!$accountConfig instanceof Account) {
            return null;
        }

        return $accountConfig->currency_id;
    }
}
