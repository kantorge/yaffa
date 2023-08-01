<?php

namespace App\Console\Commands;

use Artisan;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ResetDatabase extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:database:reset';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reset app database to an initial state to remove visitor modifications';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        Artisan::call('down');
        Artisan::call('migrate:fresh', ['--force' => true]);

        // The migrate:fresh command creates a base user. Now we need to load the demo.sql file into the database.
        $file = base_path('database/demo.sql');
        DB::unprepared(file_get_contents($file));

        /**
         * Now we need to adjust ALL dates in the database to be the current date.
         * Created_at and updated_at fields are ignored, as it is not used at the moment by the app.
         * First, calculate the difference between the current date and the date hard coded here,
         * which represents the latest date in the demo data. We need the difference in months.
         * Then, add that difference to every date in the database.
         */
        $date = '2008-12-31';
        $diff = date_diff(date_create($date), date_create(date('Y-m-d')));

        // Update all dates in the database
        // transactions - date
        DB::table('transactions')
            ->update(['date' => DB::raw("DATE_ADD(date, INTERVAL {$diff->m} MONTH)")]);

        // transsaction_schedules - start_date, next_date, end_date
        DB::table('transaction_schedules')
            ->update([
                'start_date' => DB::raw("DATE_ADD(start_date, INTERVAL {$diff->m} MONTH)"),
                'next_date' => DB::raw("DATE_ADD(next_date, INTERVAL {$diff->m} MONTH)"),
                'end_date' => DB::raw("DATE_ADD(end_date, INTERVAL {$diff->m} MONTH)"),
            ]);

        // Finally, run automated data retrieval commands to populate the database with current data.
        Artisan::call('app:investment-prices:get');
        Artisan::call('up');

        return Command::SUCCESS;
    }
}
