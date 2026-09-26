<?php

namespace App\Console\Commands;

use App\Jobs\CalculateBudgetActiveFlag;
use App\Jobs\CalculateTransactionScheduleActiveFlag;
use App\Models\Budget;
use App\Models\Transaction;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:cache:transaction-schedule-active-flags')]
#[Description('Recalculate and cache the active flags for all transaction schedules and budgets.')]
class CalculateTransactionScheduleActiveFlags extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        Transaction::with('transactionSchedule')
            ->isSchedule()
            ->lazy()
            ->each(function ($transaction) {
                CalculateTransactionScheduleActiveFlag::dispatch($transaction);
            });

        // Standalone budgets have their own active flag, computed the same way (FR-4).
        Budget::lazy()->each(function (Budget $budget) {
            CalculateBudgetActiveFlag::dispatch($budget);
        });
    }
}
