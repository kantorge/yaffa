<?php

namespace App\Console;

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

        // Run the investment price retrieval command
        $schedule->command('app:investment-prices:get')->dailyAt('05:00');

        // Run the currency rate retrieval command
        $schedule->command('app:currency-rates:get')->dailyAt('06:00');

        // Run the command to record scheduled transactions
        $schedule->command('app:record-scheduled-transactions')->dailyAt('06:00');
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
