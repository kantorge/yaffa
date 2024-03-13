<?php

namespace App\Console;

use App\Console\Commands\CalculateAccountMonthlySummaries;
use App\Console\Commands\CalculateTransactionScheduleActiveFlags;
use App\Console\Commands\GetCurrencyRates;
use App\Console\Commands\GetInvestmentPrices;
use App\Console\Commands\ProcessIncomingEmails;
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
    protected $commands = [
        CalculateAccountMonthlySummaries::class,
        CalculateTransactionScheduleActiveFlags::class,
        GetCurrencyRates::class,
        GetInvestmentPrices::class,
        ProcessIncomingEmails::class,
        RecordScheduledTransactions::class,
    ];

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

        // Redis cache cleanup
        $schedule->command('cache:prune-stale-tags')->hourly();

        // Batch job cleanup
        $schedule->command('queue:prune-batches')->daily();
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
