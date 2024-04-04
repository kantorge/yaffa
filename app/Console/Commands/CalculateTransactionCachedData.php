<?php

namespace App\Console\Commands;

use App\Models\Transaction;
use App\Services\TransactionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class CalculateTransactionCachedData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:calculate-transaction-cached-data';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Loop all transactions and calculate the cached data for each one.';

    private TransactionService $transactionService;

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $this->transactionService = new TransactionService();

        // Get the total number of transactions
        $totalTransactions = Transaction::count();

        // Create a new progress bar instance
        $progressBar = $this->output->createProgressBar($totalTransactions);

        // Output initial message
        $this->info('Calculating cached data for all transactions...');

        // Get all transactions of all users in chunks
        Transaction::chunk(200, function ($transactions) use ($progressBar) {
            foreach ($transactions as $transaction) {
                // First, make sure to update the currency_id and cashflow_value columns
                $transaction->currency_id = $this->transactionService->getTransactionCurrencyId($transaction);
                $transaction->cashflow_value = $this->transactionService->getTransactionCashFlow($transaction);
                $transaction->saveQuietly();

                // Advance the progress bar by one step
                $progressBar->advance();
            }
        });

        // Finish the progress bar
        $progressBar->finish();

        // Output final message
        $this->info('Cached data for all transactions has been calculated.');
        $this->info('Triggering the command to calculate the monthly summaries...');

        // Finally, trigger the command to calculate the monthly summaries
        Artisan::call('app:cache:account-monthly-summaries');
    }
}
