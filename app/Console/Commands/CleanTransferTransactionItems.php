<?php

namespace App\Console\Commands;

use App\Models\Transaction;
use App\Models\TransactionItem;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanTransferTransactionItems extends Command
{
    protected $signature = 'transactions:clean-transfer-items {--dry-run : Show what would be deleted without deleting}';

    protected $description = 'Remove transaction items from transfer transactions (type 3) which should not have items';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        // Find all transfer transactions (type 3) that have transaction items
        $transfersWithItems = Transaction::where('transaction_type_id', 3)
            ->has('transactionItems')
            ->with('transactionItems')
            ->get();

        $this->info("Found {$transfersWithItems->count()} transfer transactions with items");

        if ($transfersWithItems->isEmpty()) {
            $this->info('No transfer transactions with items found. Nothing to clean up.');
            return Command::SUCCESS;
        }

        $totalItems = 0;
        foreach ($transfersWithItems as $transaction) {
            $itemCount = $transaction->transactionItems->count();
            $totalItems += $itemCount;
            
            $this->line("Transaction #{$transaction->id} (Date: {$transaction->date}) has {$itemCount} items");
        }

        $this->newLine();
        $this->warn("Total transaction items to delete: {$totalItems}");

        if ($dryRun) {
            $this->info('DRY RUN - No items were actually deleted');
            return Command::SUCCESS;
        }

        if (!$this->confirm('Do you want to delete these transaction items?')) {
            $this->info('Operation cancelled');
            return Command::SUCCESS;
        }

        $deletedCount = 0;
        foreach ($transfersWithItems as $transaction) {
            $itemIds = $transaction->transactionItems->pluck('id')->toArray();
            
            // Delete transaction_items_tags pivot entries first
            DB::table('transaction_items_tags')
                ->whereIn('transaction_item_id', $itemIds)
                ->delete();
            
            // Delete transaction items
            $deleted = TransactionItem::whereIn('id', $itemIds)->delete();
            $deletedCount += $deleted;
            
            $this->line("Deleted {$deleted} items from transaction #{$transaction->id}");
        }

        $this->newLine();
        $this->info("Successfully deleted {$deletedCount} transaction items from {$transfersWithItems->count()} transfer transactions");

        return Command::SUCCESS;
    }
}
