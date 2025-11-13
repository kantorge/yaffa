<?php

namespace App\Observers;

use App\Models\Transaction;
use App\Models\TransactionDetailInvestment;
use App\Models\TransactionType;

class TransactionObserver
{
    /**
     * Handle the Transaction "created" event.
     */
    public function created(Transaction $transaction): void
    {
        $this->handleInterestReInvest($transaction);
    }

    /**
     * Handle the Transaction "updated" event.
     */
    public function updated(Transaction $transaction): void
    {
        //
    }

    /**
     * Handle the Transaction "deleted" event.
     */
    public function deleted(Transaction $transaction): void
    {
        //
    }

    /**
     * Handle the Transaction "restored" event.
     */
    public function restored(Transaction $transaction): void
    {
        //
    }

    /**
     * Handle the Transaction "force deleted" event.
     */
    public function forceDeleted(Transaction $transaction): void
    {
        //
    }

    /**
     * When an "Interest ReInvest" transaction is created, automatically create two transactions:
     * 1. Interest yield transaction (for the dividend/interest income)
     * 2. Add shares transaction (for reinvesting at price 1)
     */
    private function handleInterestReInvest(Transaction $transaction): void
    {
        // Only process Interest ReInvest transactions
        if ($transaction->transaction_type_id !== 13) {
            return;
        }

        // Ensure this is an investment transaction
        if (!$transaction->isInvestment()) {
            return;
        }

        // Check if we're already processing (to prevent infinite loops)
        if ($transaction->getAttribute('_processing_reinvest')) {
            return;
        }

        $config = $transaction->config;

        // Get the transaction type IDs
        $interestYieldType = TransactionType::where('name', 'Interest yield')->first();
        $addSharesType = TransactionType::where('name', 'Add shares')->first();

        if (!$interestYieldType || !$addSharesType) {
            return;
        }

        // Create the Interest Yield transaction
        $interestYieldConfig = TransactionDetailInvestment::create([
            'account_id' => $config->account_id,
            'investment_id' => $config->investment_id,
            'price' => null,
            'quantity' => null,
            'commission' => $config->commission,
            'tax' => $config->tax,
            'dividend' => $config->dividend,
        ]);

        $interestTransaction = new Transaction([
            'user_id' => $transaction->user_id,
            'date' => $transaction->date,
            'transaction_type_id' => $interestYieldType->id,
            'config_type' => 'investment',
            'config_id' => $interestYieldConfig->id,
            'schedule' => $transaction->schedule,
            'budget' => $transaction->budget,
            'reconciled' => $transaction->reconciled,
            'comment' => $transaction->comment ? $transaction->comment . ' (Interest)' : 'Interest from ReInvest',
        ]);
        $interestTransaction->setAttribute('_processing_reinvest', true);
        $interestTransaction->save();

        // Create the Add Shares transaction
        $addSharesConfig = TransactionDetailInvestment::create([
            'account_id' => $config->account_id,
            'investment_id' => $config->investment_id,
            'price' => 1,
            'quantity' => $config->dividend, // quantity equals the dividend amount
            'commission' => null,
            'tax' => null,
            'dividend' => null,
        ]);

        $sharesTransaction = new Transaction([
            'user_id' => $transaction->user_id,
            'date' => $transaction->date,
            'transaction_type_id' => $addSharesType->id,
            'config_type' => 'investment',
            'config_id' => $addSharesConfig->id,
            'schedule' => $transaction->schedule,
            'budget' => $transaction->budget,
            'reconciled' => $transaction->reconciled,
            'comment' => $transaction->comment ? $transaction->comment . ' (Shares)' : 'Shares from ReInvest',
        ]);
        $sharesTransaction->setAttribute('_processing_reinvest', true);
        $sharesTransaction->save();

        // Delete the original Interest ReInvest transaction since we've created the two native ones
        // Use forceDelete to bypass soft deletes if any, and prevent triggering deleted event processing
        $transaction->config->delete();
        $transaction->forceDelete();
    }
}
