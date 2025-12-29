<?php

namespace App\Console\Commands;

use App\Jobs\RecordScheduledTransaction;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Console\Command;

class RecordScheduledTransactions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:record-scheduled-transactions';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Enter transactions in the database which are due, and automatic recording is needed.';

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

        Transaction::byScheduleType('schedule')
            ->whereHas('transactionSchedule', function ($query) {
                $query->where('next_date', '<=', Carbon::now())
                    ->where('automatic_recording', true);
            })
            ->get()
            ->each(function ($transaction) {
                RecordScheduledTransaction::dispatch($transaction);
            });

        return Command::SUCCESS;
    }
}
