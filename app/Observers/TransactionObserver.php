<?php

namespace App\Observers;

use App\Models\Transaction;
use App\Models\TransactionDetailInvestment;
use App\Services\InvestmentService;

class TransactionObserver
{
    protected InvestmentService $investmentService;

    public function __construct()
    {
        $this->investmentService = new InvestmentService();
    }

    /**
     * Handle the Transaction "created" event.
     */
    public function created(Transaction $transaction): void
    {
        $this->updateBondSchedulesIfNeeded($transaction);
    }

    /**
     * Handle the Transaction "updated" event.
     */
    public function updated(Transaction $transaction): void
    {
        $this->updateBondSchedulesIfNeeded($transaction);
    }

    /**
     * Handle the Transaction "deleted" event.
     */
    public function deleted(Transaction $transaction): void
    {
        $this->updateBondSchedulesIfNeeded($transaction);
    }

    /**
     * Update bond schedules if this is an investment transaction that affects quantity
     */
    private function updateBondSchedulesIfNeeded(Transaction $transaction): void
    {
        // Only process investment transactions that are not scheduled (actual transactions)
        if ($transaction->config_type !== 'investment' || $transaction->schedule) {
            return;
        }

        // Get the investment from the transaction
        $config = $transaction->config;
        if (!$config instanceof TransactionDetailInvestment) {
            return;
        }

        $investment = $config->investment;
        if (!$investment || !$investment->isFractionalBond()) {
            return;
        }

        // Check if this transaction type affects quantity
        $transactionType = $transaction->transactionType;
        if ($transactionType && in_array($transactionType->name, ['Buy', 'Sell', 'Transfer'])) {
            $this->investmentService->updateBondSchedulesAfterQuantityChange($investment);
        }
    }
}