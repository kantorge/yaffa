<?php

namespace App\Console\Commands;

use App\Jobs\CalculateBudgetActiveFlag;
use App\Jobs\CalculateTransactionScheduleActiveFlag;
use App\Models\Budget;
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
    protected $description = 'Recalculate and cache the active flags for all transaction schedules and budgets.';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        // Get all transactions which are real schedules
        $transactions = Transaction::with('transactionSchedule')
            ->isSchedule()
            ->get();

        $transactions->each(function ($transaction) {
            CalculateTransactionScheduleActiveFlag::dispatch($transaction);
        });

        // Standalone budgets have their own active flag, computed the same way (FR-4).
        Budget::all()->each(function (Budget $budget) {
            CalculateBudgetActiveFlag::dispatch($budget);
        });
    }
}
