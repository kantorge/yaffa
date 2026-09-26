<?php

namespace App\Console\Commands;

use App\Jobs\RecordScheduledTransaction;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:record-scheduled-transactions')]
#[Description('Enter transactions in the database which are due, and automatic recording is needed.')]
class RecordScheduledTransactions extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        /**
         * Get transactions with the following criteria
         * - scheduled
         * - next_date of the schedule settings is today or earlier
         * - automatic recording is enabled
         */

        Transaction::isSchedule()
            ->whereHas('transactionSchedule', function ($query) {
                $query->where('next_date', '<=', Carbon::today()->toDateString())
                    ->where('automatic_recording', true);
            })
            ->get()
            ->each(function ($transaction) {
                RecordScheduledTransaction::dispatch($transaction);
            });

        return Command::SUCCESS;
    }
}
