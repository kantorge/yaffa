<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class ResetDemoDatabase extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:sandbox:reset-database
        {--skip-date-adjustment : Skip adjusting dates in the database}
        {--force-sandbox : Allow running this command even if sandbox mode is not enabled}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reset the demo (sandbox) database to an initial state to remove visitor modifications';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        // This command cannot be run if sandbox mode is not enabled
        if (!config('yaffa.sandbox_mode') && ! $this->option('force-sandbox')) {
            $this->error('This command can only be run in sandbox mode.');
            return Command::FAILURE;
        }

        $this->info('Putting site in maintenance mode...');
        Artisan::call('down');

        // As part of the reset process, we also need to clear all caches to avoid any issues with stale data after the reset.
        $this->info('Clearing caches...');
        Artisan::call('cache:clear');

        // Additionally, remove the AI Document files from storage. We can safely remove all files
        $this->info('Removing AI Document files from storage...');
        $file = new Filesystem();
        $file->cleanDirectory(storage_path('app/ai_documents'));

        // Actually rebuild the database
        $this->info('Resetting database...');
        Artisan::call('migrate:fresh', ['--force' => true]);

        // Create the demo user without using factory, which is not autoloaded in production
        // The generated user ID is expected to be 1
        $this->info('Creating demo user...');
        $demoUser = User::create([
            'id' => 1,
            'name' => 'Demo User',
            'email' => 'demo@yaffa.cc',
            'password' => Hash::make('demo'),
            'language' => 'en',
            'locale' => 'en-US',
        ]);
        $demoUser->markEmailAsVerified();

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

        // Create a set of sample received mails for the demo user, which can be used to test the email processing features of the app.
        $this->info('Creating sample received mails...');
        $this->createSampleReceivedMails();

        // Create AI Provider Config for demo user, if provided
        if (config('demo.ai_api_key')) {
            // Ensure we don't have multiple configs for the demo user, until this constraint is active
            $demoUser->aiProviderConfigs()->delete();
            $demoUser->aiProviderConfigs()->create([
                'provider' => config('demo.ai_provider', 'openai'),
                'model' => config('demo.ai_model', 'gpt-4o-mini'),
                'api_key' => config('demo.ai_api_key'),
                'vision_enabled' => true,
            ]);
            $this->info('AI Provider Config created.');
        } else {
            $this->warn('Skipping AI Provider Config - DEMO_AI_API_KEY not set');
        }

        // Create Google Drive Config for demo user, if provided
        if (config('demo.google_drive_json_key_file')) {
            $demoUser->googleDriveConfigs()->delete();

            $keyFileContent = file_get_contents(config('demo.google_drive_json_key_file'));
            $credentials = json_decode($keyFileContent, true);
            $demoUser->googleDriveConfigs()->create([
                'service_account_email' => $credentials['client_email'] ?? null,
                'folder_id' => config('demo.google_drive_folder_id'),
                'service_account_json' => $keyFileContent,
            ]);
            $this->info('Google Drive Config created.');
        } else {
            $this->warn('Skipping Google Drive Config - DEMO_GOOGLE_DRIVE_JSON_KEY not set');
        }

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

    private function createSampleReceivedMails(): void
    {
        $this->createSampleReceivedMailsForDemoUser([
            'subject' => 'Sample Incoming Email - HTML and Text',
            'text' => 'This is a sample plain text body of the email.',
            'html' => '<p>This is a sample <strong>HTML</strong> body of the email.</p>',
        ]);

        $this->createSampleReceivedMailsForDemoUser([
            'subject' => 'Sample Incoming Email - Text Only',
            'text' => 'This is a sample plain text body of the email without HTML version.',
            'html' => '',
        ]);

        $this->createSampleReceivedMailsForDemoUser([
            'subject' => 'Sample Incoming Email - HTML Only',
            'text' => '',
            'html' => '<p>This is a sample <strong>HTML</strong> body of the email without plain text version.</p>',
        ]);

        // TODO: read account and payee from actual demo assets
        $this->createSampleReceivedMailsForDemoUser([
            'subject' => 'Sample Incoming Email - Easy to Process by AI',
            'text' => 'Date: ' . Carbon::now()->format('Y-m-d') . "\n" .
                'Amount: 123.45 USD' . "\n" .
                'Account: Bank Account - John' . "\n" .
                'Payee: AquaFlow Utilities' . "\n",
            'html' => '',
        ]);

        $this->createSampleReceivedMailsForDemoUser([
            'subject' => 'Sample Incoming Email - Easy to Process by AI with known and unknown item categories',
            'text' => 'Total amount 100 USD, paid with "Credit Card - John" at "DIY Depot" on 2026-02-01.' . "\n" .
                'The amount is for the following items:' . "\n" .
                '- Hammer: 25 USD' . "\n" .  // Should be categorizes as Household / Generic household equipment (learning available)
                '- Coca Cola .5 litres: 5 USD' . "\n" . // Ideally, this should be categorized as Food / Beverages (no learning available)
                '- Absolutely Unknown Item 1: 30 USD' . "\n" .
                '- Absolutely Unknown Item 2: 40 USD' . "\n",
            'html' => '',
        ]);
    }

    private function createSampleReceivedMailsForDemoUser(array $mailParams): void
    {
        Artisan::call('app:simulate-incoming-email', [
            '--from' => 'demo@yaffa.cc',
            '--subject' => $mailParams['subject'] ?? 'Sample Incoming Email',
            '--text' => $mailParams['text'] ?? null,
            '--html' => $mailParams['html'] ?? null,
            '--message-id' => $mailParams['message_id'] ?? 'sample-email-' . uniqid(),
            '--user-id' => 1,
        ]);
    }
}
