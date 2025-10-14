<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class ResetDemoDatabase extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:sandbox:reset-database '
        . '{--skip-date-adjustment : Skip adjusting dates in the database}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reset the demo (sandbox) database to an initial state to remove visitor modifications';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        // This command cannot be run if sandbox mode is not enabled
        if (!config('yaffa.sandbox_mode')) {
            $this->error('This command can only be run in sandbox mode.');
            return Command::FAILURE;
        }

        $this->info('Putting site in maintenance mode...');
        Artisan::call('down');

        $this->info('Resetting database...');
        Artisan::call('migrate:fresh', ['--force' => true]);

        // Create the demo user without using factory, which is not autoloaded in production
        // The generated user ID is expected to be 1
        $this->info('Creating demo user...');
        User::create([
            'id' => 1,
            'name' => 'Demo User',
            'email' => 'demo@yaffa.cc',
            'password' => Hash::make('demo'),
            'language' => 'en',
            'locale' => 'en-US',
        ])->markEmailAsVerified();

        // There are assets in the database for a test user with ID 2
        User::create([
            'id' => 2,
            'name' => 'Test User',
            'email' => 'test@yaffa.cc',
            'password' => Hash::make('test'),
            'language' => 'en',
            'locale' => 'en-US',
            'email_verified_at' => Carbon::now(),
        ])->markEmailAsVerified();

        // Now we need to load the demo.sql file into the database.
        // We assume the database to be empty in terms of users and user related data, except the demo user (1).
        $this->info('Loading demo data from file...');
        $file = base_path('database/seeders/demo.sql');
        DB::unprepared(file_get_contents($file));
        $this->info('Demo data loaded.');

        /**
         * Unless explicitly disabled, we need to adjust ALL dates in the database to be the current date.
         * Created_at and updated_at fields are ignored, as it is not used at the moment by the app.
         * First, calculate the difference between the current date and the date hard coded here,
         * which represents the latest date in the demo data. We need the difference in months.
         * Then, add that difference to every date in the database.
         */
        if (!$this->option('skip-date-adjustment')) {
            $this->info('Adjusting dates in the database...');
            $date = '2008-12-31';
            $diff = date_diff(date_create($date), date_create(date('Y-m-d')));
            $diffMonths = $diff->y * 12 + $diff->m + 1;

            // Update all dates in the database
            // transactions - date
            $this->info('Adjusting dates - transactions...');
            $affected = DB::table('transactions')
                ->where('user_id', 1)
                ->update(['date' => DB::raw("DATE_ADD(date, INTERVAL {$diffMonths} MONTH)")]);
            $this->info("Transactions updated: {$affected}");

            // transaction_schedules - start_date, next_date, end_date
            $affected = DB::table('transaction_schedules')
                // The schedules of the test user are also updated
                ->update([
                    'start_date' => DB::raw("DATE_ADD(start_date, INTERVAL {$diffMonths} MONTH)"),
                    'next_date' => DB::raw("DATE_ADD(next_date, INTERVAL {$diffMonths} MONTH)"),
                    'end_date' => DB::raw("DATE_ADD(end_date, INTERVAL {$diffMonths} MONTH)"),
                ]);
            $this->info("Transaction schedules updated: {$affected}");
        }

        // Next, run automated data retrieval commands to populate the database with current data.
        $this->info('Retrieving investment data...');
        Artisan::call('app:investment-prices:get');

        // Next, run the commands to recalculate various stored data
        Artisan::call('app:cache:transaction-schedule-active-flags');
        Artisan::call('app:calculate-transaction-cached-data');
        Artisan::call('app:cache:account-monthly-summaries');

        // Finally, put the site live
        $this->info('Database refresh ready, putting site live...');
        Artisan::call('up');

        return Command::SUCCESS;
    }
}
