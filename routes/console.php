<?php

use App\Console\Commands\CalculateAccountMonthlySummaries;
use App\Console\Commands\CalculateTransactionScheduleActiveFlags;
use App\Console\Commands\GetCurrencyRates;
use App\Console\Commands\GetInvestmentPrices;
use App\Console\Commands\RecordScheduledTransactions;
use Illuminate\Support\Facades\Schedule;


if ($this->app->environment('local')) {
    Schedule::command('telescope:prune')->daily();
}

// Potentially, the app can be separated into a main container and a worker container
// We can control, if the scheduled commands need to be run in a given container
if (config('yaffa.runs_scheduler')) {

    // Recalculate the active flags for transaction schedules and budgets every day
    Schedule::command(CalculateTransactionScheduleActiveFlags::class)->dailyAt('00:00');

    // Run the command to record scheduled transactions
    // This can also modify the active flag of the schedule, handled by the TransactionSchedule model
    Schedule::command(RecordScheduledTransactions::class)->dailyAt('00:05');

    // Run the currency rate retrieval command
    Schedule::command(GetCurrencyRates::class)->dailyAt('04:00');

    // Run the investment price retrieval command
    Schedule::command(GetInvestmentPrices::class)->dailyAt('04:15');

    // Recalculate account monthly summaries daily
    // TODO: chain this command with the investment price retrieval command
    Schedule::command(CalculateAccountMonthlySummaries::class)->dailyAt('05:00');

    // Only in sandbox mode - reset the sandbox database at 2am UTC on Monday, Wednesday, and Friday
    // This should be a balance between keeping the data fresh and allowing users to experiment with it
    if (config('yaffa.sandbox_mode')) {
        Schedule::command('app:sandbox:reset-database')->weeklyOn([1, 3, 5], '02:00');
    }

    // Redis cache cleanup
    Schedule::command('cache:prune-stale-tags')->hourly();

    // Batch job cleanup
    Schedule::command('queue:prune-batches')->daily();
}
