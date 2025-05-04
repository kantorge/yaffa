<?php

namespace App\Console;

use App\Console\Commands\CalculateAccountMonthlySummaries;
use App\Console\Commands\CalculateTransactionScheduleActiveFlags;
use App\Console\Commands\GetCurrencyRates;
use App\Console\Commands\GetInvestmentPrices;
use App\Console\Commands\RecordScheduledTransactions;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [];

    /**
     * Define the application's command schedule.
     *
     * @param Schedule $schedule
     */
    protected function schedule(Schedule $schedule): void
    {
        if ($this->app->environment('local')) {
            $schedule->command('telescope:prune')->daily();
        }

        // Potentially, the app can be separated into a main container and a worker container
        // We can control, if the scheduled commands need to be run in a given container
        if (env('RUNS_SCHEDULER', false)) {

            // Recalculate the active flags for transaction schedules and budgets every day
            $schedule->command(CalculateTransactionScheduleActiveFlags::class)->dailyAt('00:00');

            // Run the command to record scheduled transactions
            // This can also modify the active flag of the schedule, handled by the TransactionSchedule model
            $schedule->command(RecordScheduledTransactions::class)->dailyAt('00:05');

            // Run the currency rate retrieval command
            $schedule->command(GetCurrencyRates::class)->dailyAt('04:00');

            // Run the investment price retrieval command
            $schedule->command(GetInvestmentPrices::class)->dailyAt('04:15');

            // Recalculate account monthly summaries daily
            // TODO: chain this command with the investment price retrieval command
            $schedule->command(CalculateAccountMonthlySummaries::class)->dailyAt('05:00');

            // Only in sandbox mode - reset the sandbox database at 2am UTC on Monday, Wednesday, and Friday
            // This should be a balance between keeping the data fresh and allowing users to experiment with it
            if (config('yaffa.sandbox_mode')) {
                $schedule->command('app:sandbox:reset-database')->weeklyOn([1, 3, 5], '02:00');
            }

            // Redis cache cleanup
            $schedule->command('cache:prune-stale-tags')->hourly();

            // Batch job cleanup
            $schedule->command('queue:prune-batches')->daily();
        }
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
