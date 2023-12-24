<?php

namespace App\Services;

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
}
