<?php

namespace App\Listeners;

use App\Services\TransactionService;

class ProcessTransactionDeleted
{
    protected TransactionService $transactionService;

    /**
     * Handle the event.
     */
    public function handle(object $event): void
    {
        $this->transactionService = new TransactionService();

        // Remove the configuration
        $event->transaction->config->delete();

        // Recalculate the relevant monthly summaries
        $this->transactionService->recalculateMonthlySummaries($event->transaction);
    }
}
