<?php

namespace App\Listeners;

use App\Enums\TransactionType as TransactionTypeEnum;
use App\Events\TransactionUpdated;
use App\Jobs\CalculateAccountMonthlySummary;
use App\Models\Transaction;
use App\Models\TransactionDetailInvestment;
use App\Models\TransactionDetailStandard;
use App\Services\TransactionService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;

class ProcessTransactionUpdated
{
    public const string CALCULATE_MONHTLY_SUMMARIES_COMMAND_SIGNATURE = 'app:cache:account-monthly-summaries';
    public const string CALCULATE_MONTHLY_SUMMARIES_ACCOUNT_PARAM = 'accountEntityId';
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

        if (! $transaction->config instanceof TransactionDetailInvestment) {
            return;
        }

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
            'config.accountFrom',
            'config.accountTo',
        ]);

        if (! $transaction->config instanceof TransactionDetailStandard) {
            return;
        }

        // Check if the account_from_id has been changed
        if (array_key_exists('config', $changedAttributes)
            && array_key_exists('account_from_id', $changedAttributes['config'])) {
            // Additionally check if the transaction type is withdrawal or transfer
            if ($transaction->transaction_type === TransactionTypeEnum::WITHDRAWAL
                || $transaction->transaction_type === TransactionTypeEnum::TRANSFER) {
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
            if ($transaction->transaction_type === TransactionTypeEnum::DEPOSIT
                || $transaction->transaction_type === TransactionTypeEnum::TRANSFER) {
                // Invoke the monthly summary calculation command for the old account
                Artisan::call(self::CALCULATE_MONHTLY_SUMMARIES_COMMAND_SIGNATURE, [
                    self::CALCULATE_MONTHLY_SUMMARIES_ACCOUNT_PARAM => $changedAttributes['config']['account_to_id']
                ]);
            }
        }

        // If the date changed for a non-scheduled, non-budget transaction, the old month must also be
        // recalculated for accounts that stayed the same — removing the transaction's stale contribution.
        // (Accounts that were replaced are already fully recalculated by the Artisan calls above.)
        if (! $transaction->schedule && ! $transaction->budget
            && isset($changedAttributes['transaction']['date'])) {
            $oldDate = Carbon::parse($changedAttributes['transaction']['date']);

            if (! $oldDate->isSameMonth($transaction->date)) {
                $accountFrom = $transaction->config->accountFrom;
                $accountTo = $transaction->config->accountTo;

                $accountFromReplaced = array_key_exists('config', $changedAttributes)
                    && array_key_exists('account_from_id', $changedAttributes['config']);
                $accountToReplaced = array_key_exists('config', $changedAttributes)
                    && array_key_exists('account_to_id', $changedAttributes['config']);

                if ($accountFrom?->isAccount() && ! $accountFromReplaced) {
                    dispatch_sync(new CalculateAccountMonthlySummary(
                        $transaction->user,
                        'account_balance-fact',
                        $accountFrom,
                        $oldDate->clone()->startOfMonth(),
                        $oldDate->clone()->endOfMonth()
                    ));
                }

                if ($accountTo?->isAccount() && ! $accountToReplaced) {
                    dispatch_sync(new CalculateAccountMonthlySummary(
                        $transaction->user,
                        'account_balance-fact',
                        $accountTo,
                        $oldDate->clone()->startOfMonth(),
                        $oldDate->clone()->endOfMonth()
                    ));
                }
            }
        }

        // Recalculate the monthly summaries for the current state of the transaction.
        // recalculateMonthlySummaries correctly handles all transaction types (including transfer, which
        // updates both accounts), and uses targeted sync dispatch for non-scheduled transactions so
        // results are immediately available without a full account-wide recalculation.
        $this->transactionService->recalculateMonthlySummaries($transaction);
    }
}
