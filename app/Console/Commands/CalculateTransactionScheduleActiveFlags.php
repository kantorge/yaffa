<?php

namespace App\Console\Commands;

use App\Jobs\CalculateTransactionScheduleActiveFlag;
use App\Models\Transaction;
use Illuminate\Console\Command;

class CalculateTransactionScheduleActiveFlags extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:cache:transaction-schedule-active-flags';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Recalculate and cache the active flags for all transaction schedules or budgets.';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        // Get all transactions which are schedules or budgets
        $transactions = Transaction::with('transactionSchedule')
            ->byScheduleType('any')
            ->get();

        $transactions->each(function ($transaction) {
            CalculateTransactionScheduleActiveFlag::dispatch($transaction);
        });
    }
}
