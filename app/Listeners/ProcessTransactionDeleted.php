<?php

namespace App\Listeners;

use App\Events\TransactionDeleted;
use App\Jobs\RecalculatePayeeProfile;
use App\Services\CategoryWaterfallCacheService;
use App\Services\TransactionService;

class ProcessTransactionDeleted
{
    protected TransactionService $transactionService;

    /**
     * Handle the event.
     */
    public function handle(TransactionDeleted $event): void
    {
        $this->transactionService = new TransactionService();

        // The payees have to be read before the configuration is removed
        RecalculatePayeeProfile::dispatchForTransaction($event->transaction);

        // Remove the configuration
        $event->transaction->config->delete();

        // Recalculate the relevant monthly summaries
        $this->transactionService->recalculateMonthlySummaries($event->transaction);

        CategoryWaterfallCacheService::forgetForDate($event->transaction->user_id, $event->transaction->date);
    }
}
