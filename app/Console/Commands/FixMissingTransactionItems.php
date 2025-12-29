<?php

namespace App\Console\Commands;

use App\Models\Transaction;
use App\Models\TransactionItem;
use Illuminate\Console\Command;

class FixMissingTransactionItems extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'yaffa:fix-missing-transaction-items 
                            {--dry-run : Show what would be fixed without making changes}
                            {--transaction-id= : Fix a specific transaction ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Find and fix transactions (type 1 and 2) missing transaction items';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $dryRun = $this->option('dry-run');
        $transactionId = $this->option('transaction-id');

        $this->info('Scanning for transactions missing transaction items...');
        if ($dryRun) {
            $this->warn('DRY RUN MODE - No changes will be made');
        }

        // Build query for standard transactions (type 1 = withdrawal, type 2 = deposit)
        $query = Transaction::with([
            'config',
            'transactionItems',
            'config.accountFrom.config',
            'config.accountTo.config',
        ])
            ->whereIn('transaction_type_id', [1, 2])
            ->where('config_type', 'standard')
            ->whereDoesntHave('transactionItems');

        if ($transactionId) {
            $query->where('id', $transactionId);
        }

        $transactions = $query->get();

        if ($transactions->isEmpty()) {
            $this->info('No transactions found missing transaction items.');
            return 0;
        }

        $this->info("Found {$transactions->count()} transactions missing transaction items:");
        $this->newLine();

        $fixed = 0;
        $errors = 0;

        foreach ($transactions as $transaction) {
            // Determine the payee based on transaction type
            // For withdrawals (type 1): payee is account_to
            // For deposits (type 2): payee is account_from
            $payee = null;
            if ($transaction->transaction_type_id === 1) {
                $payee = $transaction->config->accountTo;
            } elseif ($transaction->transaction_type_id === 2) {
                $payee = $transaction->config->accountFrom;
            }

            // Get the default category from the payee
            $categoryId = null;
            if ($payee) {
                // Check if payee has a config (Payee model) with a default category_id
                if ($payee->config && $payee->config->category_id) {
                    $categoryId = $payee->config->category_id;
                }
            }

            $this->line("Transaction ID: {$transaction->id}");
            $this->line("  Date: {$transaction->date}");
            $this->line("  Type: {$transaction->transactionType->name} (ID: {$transaction->transaction_type_id})");
            $this->line("  Amount: {$transaction->cashflow_value}");
            $this->line("  Payee: " . ($payee?->name ?? 'N/A'));
            $this->line("  Category ID: " . ($categoryId ?? 'NONE - WILL SKIP'));
            $this->line("  Comment: " . ($transaction->comment ?? 'N/A'));

            if (!$categoryId) {
                $this->warn("  ⚠ Skipping - no preferred category found for payee");
                $errors++;
                $this->newLine();
                continue;
            }

            if (!$dryRun) {
                try {
                    // Create a transaction item with the full amount and payee's default category
                    TransactionItem::create([
                        'transaction_id' => $transaction->id,
                        'amount' => abs($transaction->cashflow_value ?? 0),
                        'category_id' => $categoryId,
                        'comment' => null,
                    ]);
                    $this->info("  ✓ Created transaction item");
                    $fixed++;
                } catch (\Exception $e) {
                    $this->error("  ✗ Error: " . $e->getMessage());
                    $errors++;
                }
            } else {
                $this->comment("  → Would create item with amount: " . abs($transaction->cashflow_value ?? 0) . " and category_id: {$categoryId}");
                $fixed++;
            }

            $this->newLine();
        }

        $this->newLine();
        if ($dryRun) {
            $this->info("DRY RUN: Would fix {$fixed} transactions");
        } else {
            $this->info("Successfully fixed {$fixed} transactions");
            if ($errors > 0) {
                $this->error("Failed to fix {$errors} transactions");
            }
        }

        return 0;
    }
}
