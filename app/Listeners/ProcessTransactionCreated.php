<?php

namespace App\Listeners;

use App\Events\TransactionCreated;
use App\Services\TransactionService;

class ProcessTransactionCreated
{
    protected TransactionService $transactionService;

    /**
     * Handle the event.
     */
    public function handle(TransactionCreated $event): void
    {
        $this->transactionService = new TransactionService();

        $transaction = $event->transaction;

        // First, make sure to update the currency_id and cashflow_value columns
        $transaction->currency_id = $this->transactionService->getTransactionCurrencyId($transaction);
        $transaction->cashflow_value = $this->transactionService->getTransactionCashFlow($transaction);
        $transaction->saveQuietly();

        // Recalculate the relevant monthly summaries
        $this->transactionService->recalculateMonthlySummaries($transaction);
    }
}
