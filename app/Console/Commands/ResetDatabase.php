<?php

namespace App\Console\Commands;

use Artisan;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

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
        $this->info('Putting site in maintenance mode...');
        Artisan::call('down');

        $this->info('Resetting database...');
        Artisan::call('migrate:fresh', ['--force' => true]);

        // Create the demo user using factory, which is not autoloaded in production
        $this->info('Creating demo user...');
        resolve(\Database\Factories\UserFactory::class)->create([
            'name' => 'Demo User',
            'email' => 'demo@yaffa.cc',
            'password' => Hash::make('demo'),
            'language' => 'en',
            'locale' => 'en-US',
        ]);

        // Now we need to load the demo.sql file into the database.
        // We assume the database to be empty in terms of users and user related data, except the demo user (1).
        $this->info('Loading demo data from file...');
        $file = base_path('database/seeders/demo.sql');
        DB::unprepared(file_get_contents($file));
        $this->info('Demo data loaded.');

        /**
         * Now we need to adjust ALL dates in the database to be the current date.
         * Created_at and updated_at fields are ignored, as it is not used at the moment by the app.
         * First, calculate the difference between the current date and the date hard coded here,
         * which represents the latest date in the demo data. We need the difference in months.
         * Then, add that difference to every date in the database.
         */
        $this->info('Adjusting dates in the database...');
        $date = '2008-12-31';
        $diff = date_diff(date_create($date), date_create(date('Y-m-d')));
        $diffMonths = $diff->y * 12 + $diff->m + 1;

        // Update all dates in the database
        // transactions - date
        $this->info('Adjusting dates - transactions...');
        $affected = DB::table('transactions')
            ->update(['date' => DB::raw("DATE_ADD(date, INTERVAL {$diffMonths} MONTH)")]);
        $this->info("Transactions updated: {$affected}");

        // transsaction_schedules - start_date, next_date, end_date
        $affected = DB::table('transaction_schedules')
            ->update([
                'start_date' => DB::raw("DATE_ADD(start_date, INTERVAL {$diffMonths} MONTH)"),
                'next_date' => DB::raw("DATE_ADD(next_date, INTERVAL {$diffMonths} MONTH)"),
                'end_date' => DB::raw("DATE_ADD(end_date, INTERVAL {$diffMonths} MONTH)"),
            ]);
        $this->info("Transaction schedules updated: {$affected}");

        // Finally, run automated data retrieval commands to populate the database with current data.
        $this->info('Retrieving investment data...');
        Artisan::call('app:investment-prices:get');

        $this->info('Database refresh ready, putting site live...');
        Artisan::call('up');

        return Command::SUCCESS;
    }
}
