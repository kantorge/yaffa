<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\AccountEntity;
use App\Services\AmazonTransactionUploader;
use Illuminate\Console\Command;

class ImportAmazonTransactions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'amazon:import
                          {--orders= : Path to Amazon orders CSV file}
                          {--returns= : Path to Amazon returns CSV file}
                          {--account= : Account ID or name to import into}
                          {--user= : User ID (defaults to first user)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import Amazon transaction history from CSV files';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        // Get user
        $userId = $this->option('user');
        $user = $userId 
            ? User::findOrFail($userId)
            : User::first();

        if (!$user) {
            $this->error('No user found');
            return Command::FAILURE;
        }

        $this->info("Importing for user: {$user->name} (ID: {$user->id})");

        // Get or select account
        $accountOption = $this->option('account');
        $account = null;

        if ($accountOption) {
            // Try to find by ID or name
            $account = AccountEntity::where('user_id', $user->id)
                ->where('config_type', 'account')
                ->where(function($query) use ($accountOption) {
                    $query->where('id', $accountOption)
                          ->orWhere('name', 'LIKE', "%{$accountOption}%");
                })
                ->first();

            if (!$account) {
                $this->error("Account not found: {$accountOption}");
                return Command::FAILURE;
            }
        } else {
            // Let user select from their accounts
            $accounts = $user->accounts()->get();
            
            if ($accounts->isEmpty()) {
                $this->error('No accounts found for this user');
                return Command::FAILURE;
            }

            $accountNames = $accounts->pluck('name', 'id')->toArray();
            $selectedName = $this->choice(
                'Select account to import Amazon transactions into:',
                $accountNames,
                0
            );
            
            // Find the ID from the selected name
            $accountId = array_search($selectedName, $accountNames);
            $account = $accounts->firstWhere('id', $accountId);
        }

        $this->info("Using account: {$account->name} (ID: {$account->id})");

        // Initialize uploader
        $uploader = new AmazonTransactionUploader($user, $account);

        // Process orders file
        $ordersFile = $this->option('orders');
        if ($ordersFile) {
            if (!file_exists($ordersFile)) {
                $this->error("Orders file not found: {$ordersFile}");
                return Command::FAILURE;
            }

            $this->info("\nProcessing orders file: {$ordersFile}");
            $results = $uploader->processOrdersFile($ordersFile);
            $this->displayResults('Orders', $results);
        }

        // Process returns file
        $returnsFile = $this->option('returns');
        if ($returnsFile) {
            if (!file_exists($returnsFile)) {
                $this->error("Returns file not found: {$returnsFile}");
                return Command::FAILURE;
            }

            $this->info("\nProcessing returns file: {$returnsFile}");
            $results = $uploader->processReturnsFile($returnsFile);
            $this->displayResults('Returns', $results);
        }

        if (!$ordersFile && !$returnsFile) {
            $this->warn('No files specified. Use --orders and/or --returns options.');
            $this->line('');
            $this->line('Example:');
            $this->line('  php artisan amazon:import --orders=amazon.csv --returns=returns.csv --account="Amex"');
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    /**
     * Display import results
     */
    private function displayResults(string $type, array $results): void
    {
        $this->line('');
        $this->info("{$type} Import Results:");
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total', $results['total']],
                ['Processed', $results['processed']],
                ['Duplicates', $results['duplicates']],
                ['Skipped', $results['skipped']],
                ['Errors', count($results['errors'])],
            ]
        );

        if (!empty($results['errors'])) {
            $this->warn("\nErrors encountered:");
            foreach ($results['errors'] as $error) {
                $this->error(
                    "Order: {$error['order_id']} - {$error['error']}"
                );
            }
        }
    }
}
