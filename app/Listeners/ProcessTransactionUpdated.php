<?php

namespace App\Listeners;

use App\Events\TransactionUpdated;
use App\Models\Transaction;
use App\Services\TransactionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Artisan;

class ProcessTransactionUpdated implements ShouldQueue
{
    use InteractsWithQueue;

    public const string CALCULATE_MONHTLY_SUMMARIES_COMMAND_SIGNATURE = 'app:cache:account-monthly-summaries';
    public const string CALCULATE_MONTHLY_SUMMARIES_ACCOUNT_PARAM = '--accountEntityId';
    protected TransactionService $transactionService;

    /**
     * Create the event listener.
     */
    public function __construct()
    {
        $this->transactionService = new TransactionService();
    }

    /**
     * Handle the event.
     */
    public function handle(TransactionUpdated $event): void
    {
        $transaction = $event->transaction;

        // First, make sure to update the currency_id and cashflow_value columns
        $transaction->currency_id = $this->transactionService->getTransactionCurrencyId($transaction);
        $transaction->cashflow_value = $this->transactionService->getTransactionCashFlow($transaction);
        $transaction->saveQuietly();

        // Based on the type of the transaction, dispatch a job to update the relevant monthly summaries
        // We also need to determine, if the change actually requires an update to the monthly summaries
        $changedAttributes = $event->changedAttributes;

        // For an investment transaction the fact and forecast summaries need to be updated
        // We need to check if the account has been changed, as it would require a recalculation of the summaries for both accounts
        if ($transaction->isInvestment()) {
            $this->handleChangeForInvestmentTransaction($transaction, $changedAttributes);
            return;
        }

        if ($transaction->isStandard()) {
            $this->handleChangeForStandardTransaction($transaction, $changedAttributes);
        }
    }

    private function handleChangeForInvestmentTransaction(Transaction $transaction, array $changedAttributes): void
    {
        $transaction->loadMissing([
            'config',
        ]);

        // Check if any of the following has changed:
        // date, anything in the config, or anything in the schedule_config
        if (!array_key_exists('date', $changedAttributes) &&
            !array_key_exists('config', $changedAttributes) &&
            !array_key_exists('schedule_config', $changedAttributes)) {
            return;
        }

        /**
         * TODO: It's probably not efficient to recalculate all summaries for the related accounts
         * However, it's also not efficient to check which summaries need to be recalculated
         */

        // Check if the account_id in the config has been changed
        if (array_key_exists('config', $changedAttributes)
            && array_key_exists('account_id', $changedAttributes['config'])) {
            // Invoke the monthly summary calculation command for the old account
            Artisan::call(self::CALCULATE_MONHTLY_SUMMARIES_COMMAND_SIGNATURE, [
                self::CALCULATE_MONTHLY_SUMMARIES_ACCOUNT_PARAM => $changedAttributes['config']['account_id']
            ]);
        }

        // Invoke the monthly summary calculation command for the current account
        Artisan::call(self::CALCULATE_MONHTLY_SUMMARIES_COMMAND_SIGNATURE, [
            self::CALCULATE_MONTHLY_SUMMARIES_ACCOUNT_PARAM => $transaction->config->account_id
        ]);
    }

    private function handleChangeForStandardTransaction(Transaction $transaction, array $changedAttributes): void
    {
        $transaction->loadMissing([
            'config',
        ]);

        // Check if the account_from_id has been changed
        if (array_key_exists('config', $changedAttributes)
            && array_key_exists('account_from_id', $changedAttributes['config'])) {
            // Additionally check if the transaction type is withdrawal or transfer
            if ($transaction->transactionType->name === 'withdrawal'
                || $transaction->transactionType->name === 'transfer') {
                // Invoke the monthly summary calculation command for the old account
                Artisan::call(self::CALCULATE_MONHTLY_SUMMARIES_COMMAND_SIGNATURE, [
                    self::CALCULATE_MONTHLY_SUMMARIES_ACCOUNT_PARAM => $changedAttributes['config']['account_from_id']
                ]);
            }
        }

        // Check if the account_to_id has been changed
        if (array_key_exists('config', $changedAttributes)
            && array_key_exists('account_to_id', $changedAttributes['config'])) {
            // Additionally check if the transaction type is deposit or transfer
            if ($transaction->transactionType->name === 'deposit'
                || $transaction->transactionType->name === 'transfer') {
                // Invoke the monthly summary calculation command for the old account
                Artisan::call(self::CALCULATE_MONHTLY_SUMMARIES_COMMAND_SIGNATURE, [
                    self::CALCULATE_MONTHLY_SUMMARIES_ACCOUNT_PARAM => $changedAttributes['config']['account_to_id']
                ]);
            }
        }

        // Invoke the monthly summary calculation command for the current account, based on the transaction type
        if ($transaction->transactionType->name === 'deposit'
            || $transaction->transactionType->name === 'transfer') {
            Artisan::call(self::CALCULATE_MONHTLY_SUMMARIES_COMMAND_SIGNATURE, [
                self::CALCULATE_MONTHLY_SUMMARIES_ACCOUNT_PARAM => $transaction->config->account_to_id
            ]);
        } elseif ($transaction->transactionType->name === 'withdrawal'
            || $transaction->transactionType->name === 'transfer') {
            Artisan::call(self::CALCULATE_MONHTLY_SUMMARIES_COMMAND_SIGNATURE, [
                self::CALCULATE_MONTHLY_SUMMARIES_ACCOUNT_PARAM => $transaction->config->account_from_id
            ]);
        }
    }
}
