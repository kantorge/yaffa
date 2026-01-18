<?php

namespace App\Console\Commands;

use App\Models\ImportJob;
use App\Models\Transaction;
use App\Models\TransactionDetailStandard;
use App\Models\TransactionItem;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Exception;

class PurgeImport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'yaffa:purge-import 
                            {import_id : The import job ID to purge}
                            {--dry-run : Show what would be deleted without actually deleting}
                            {--force : Skip confirmation prompt}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Purge/revert all transactions from a specific import job';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $importId = $this->argument('import_id');
        $dryRun = $this->option('dry-run');
        $force = $this->option('force');

        // Find the import job
        $import = ImportJob::find($importId);
        if (! $import) {
            $this->error("Import job #{$importId} not found");
            return 1;
        }

        // Get transaction count
        $transactionCount = Transaction::where('import_job_id', $importId)->count();

        if ($transactionCount === 0) {
            $this->info("No transactions found for import job #{$importId}");
            return 0;
        }

        // Show import details
        $this->info("=== Import Job #{$importId} ===");
        $this->table(
            ['Property', 'Value'],
            [
                ['Import ID', $import->id],
                ['User ID', $import->user_id],
                ['File', basename($import->file_path)],
                ['Status', $import->status],
                ['Created', $import->created_at->format('Y-m-d H:i:s')],
                ['Processed Rows', $import->processed_rows],
                ['Transactions Found', $transactionCount],
            ]
        );

        // Get transaction details
        $transactions = Transaction::with('config')
            ->where('import_job_id', $importId)
            ->get();

        // Calculate affected accounts
        $affectedAccounts = collect();
        foreach ($transactions as $txn) {
            if ($txn->config instanceof TransactionDetailStandard) {
                if ($txn->config->account_from_id) {
                    $affectedAccounts->push($txn->config->account_from_id);
                }
                if ($txn->config->account_to_id) {
                    $affectedAccounts->push($txn->config->account_to_id);
                }
            }
        }
        $affectedAccounts = $affectedAccounts->unique();

        $this->newLine();
        $this->info("=== Impact ===");
        $this->line("Transactions to delete: <fg=red>{$transactionCount}</>");
        $this->line("Affected accounts: {$affectedAccounts->count()}");
        $this->line("Date range: {$transactions->min('date')} to {$transactions->max('date')}");

        // Show sample transactions
        if ($transactionCount > 0) {
            $this->newLine();
            $this->info("=== Sample Transactions (first 5) ===");
            $sample = $transactions->take(5);
            $rows = $sample->map(function ($txn) {
                $config = $txn->config;
                if ($config instanceof TransactionDetailStandard) {
                    $amount = $config->amount_from;
                } else {
                    $amount = 'N/A';
                }

                return [
                    $txn->id,
                    $txn->date,
                    number_format($amount, 2),
                    mb_substr($txn->comment ?? 'N/A', 0, 40),
                ];
            })->toArray();

            $this->table(['ID', 'Date', 'Amount', 'Comment'], $rows);
        }

        if ($dryRun) {
            $this->newLine();
            $this->warn('DRY RUN: No changes will be made');
            return 0;
        }

        // Confirm deletion
        if (! $force) {
            $this->newLine();
            if (! $this->confirm("Are you sure you want to delete {$transactionCount} transactions?", false)) {
                $this->info('Cancelled');
                return 0;
            }
        }

        // Perform deletion
        $this->newLine();
        $this->info('Deleting transactions...');

        DB::beginTransaction();

        try {
            $deleted = 0;
            $bar = $this->output->createProgressBar($transactionCount);
            $bar->start();

            foreach ($transactions as $transaction) {
                // Delete transaction items
                TransactionItem::where('transaction_id', $transaction->id)->delete();

                // Delete transaction config
                if ($transaction->config) {
                    $transaction->config->delete();
                }

                // Delete transaction
                $transaction->delete();

                $deleted++;
                $bar->advance();
            }

            $bar->finish();
            $this->newLine(2);

            // Update import job status
            $import->update([
                'status' => 'purged',
                'errors' => ['Purged via command at ' . now()->toDateTimeString()],
            ]);

            DB::commit();

            $this->info("✓ Successfully deleted {$deleted} transactions");
            $this->info("✓ Import job marked as 'purged'");

            // Suggest recalculating affected accounts
            if ($affectedAccounts->isNotEmpty()) {
                $this->newLine();
                $this->comment("Tip: Recalculate affected accounts:");
                $this->line("  php artisan yaffa:recalculate-accounts " . $affectedAccounts->take(3)->implode(','));
            }

            return 0;
        } catch (Exception $e) {
            DB::rollBack();
            $this->error("Failed to purge import: " . $e->getMessage());
            return 1;
        }
    }
}
