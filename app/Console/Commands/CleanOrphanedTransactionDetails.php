<?php

namespace App\Console\Commands;

use App\Models\AccountEntity;
use App\Models\TransactionDetailStandard;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanOrphanedTransactionDetails extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'transactions:clean-orphaned 
                            {--payee-id= : Clean specific payee ID}
                            {--dry-run : Show what would be deleted without deleting}
                            {--delete-unused-payees : Also delete payees with no transactions}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up orphaned transaction details and optionally unused payees';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $payeeId = $this->option('payee-id');
        $dryRun = $this->option('dry-run');
        $deleteUnusedPayees = $this->option('delete-unused-payees');

        $this->info('Scanning for orphaned transaction details...');
        $this->newLine();

        // Find orphaned transaction_details_standard records
        // These are records where no transaction references them
        $orphanedDetails = DB::table('transaction_details_standard as tds')
            ->leftJoin('transactions as t', function($join) {
                $join->on('t.config_id', '=', 'tds.id')
                     ->where('t.config_type', '=', 'standard');
            })
            ->whereNull('t.id')
            ->select('tds.*')
            ->get();

        $this->info("Found {$orphanedDetails->count()} orphaned transaction detail records");

        if ($orphanedDetails->isEmpty()) {
            $this->info('No orphaned transaction details found.');
        } else {
            // Group by account entities involved
            $affectedPayees = [];
            foreach ($orphanedDetails as $detail) {
                if ($detail->account_from_id) {
                    $affectedPayees[$detail->account_from_id] = ($affectedPayees[$detail->account_from_id] ?? 0) + 1;
                }
                if ($detail->account_to_id) {
                    $affectedPayees[$detail->account_to_id] = ($affectedPayees[$detail->account_to_id] ?? 0) + 1;
                }
            }

            $this->info("Affected account entities:");
            foreach ($affectedPayees as $accountId => $count) {
                $entity = AccountEntity::find($accountId);
                if ($entity) {
                    $this->line("  - {$entity->name} (ID: {$accountId}, Type: {$entity->config_type}): {$count} orphaned records");
                }
            }
            $this->newLine();

            if ($dryRun) {
                $this->warn("DRY RUN: Would delete {$orphanedDetails->count()} orphaned transaction detail records");
                $this->table(
                    ['ID', 'Account From', 'Account To', 'Amount From', 'Amount To'],
                    $orphanedDetails->take(10)->map(function($detail) {
                        return [
                            $detail->id,
                            $detail->account_from_id ?? '-',
                            $detail->account_to_id ?? '-',
                            $detail->amount_from ?? '-',
                            $detail->amount_to ?? '-',
                        ];
                    })->toArray()
                );
                if ($orphanedDetails->count() > 10) {
                    $this->line("... and " . ($orphanedDetails->count() - 10) . " more");
                }
            } else {
                if ($this->confirm("Delete {$orphanedDetails->count()} orphaned transaction detail records?", false)) {
                    $deleted = DB::table('transaction_details_standard')
                        ->whereIn('id', $orphanedDetails->pluck('id'))
                        ->delete();
                    
                    $this->info("Deleted {$deleted} orphaned transaction detail records");
                } else {
                    $this->info('Cancelled.');
                    return 0;
                }
            }
        }

        // Check for unused payees
        if ($deleteUnusedPayees || $payeeId) {
            $this->newLine();
            $this->info('Checking for unused payees...');

            $payeesQuery = AccountEntity::where('config_type', 'payee');
            
            if ($payeeId) {
                $payeesQuery->where('id', $payeeId);
            }

            $payees = $payeesQuery->get();
            $unusedPayees = [];

            foreach ($payees as $payee) {
                // Check if payee has any transaction details
                $hasTransactions = DB::table('transaction_details_standard as tds')
                    ->join('transactions as t', function($join) {
                        $join->on('t.config_id', '=', 'tds.id')
                             ->where('t.config_type', '=', 'standard');
                    })
                    ->where(function($query) use ($payee) {
                        $query->where('tds.account_from_id', $payee->id)
                              ->orWhere('tds.account_to_id', $payee->id);
                    })
                    ->exists();

                if (!$hasTransactions) {
                    $unusedPayees[] = $payee;
                }
            }

            if (empty($unusedPayees)) {
                $this->info('No unused payees found.');
            } else {
                $this->info("Found " . count($unusedPayees) . " unused payees:");
                foreach ($unusedPayees as $payee) {
                    $this->line("  - {$payee->name} (ID: {$payee->id})");
                }
                $this->newLine();

                if ($dryRun) {
                    $this->warn("DRY RUN: Would delete " . count($unusedPayees) . " unused payees");
                } else {
                    if ($this->confirm("Delete " . count($unusedPayees) . " unused payees?", false)) {
                        DB::beginTransaction();
                        try {
                            foreach ($unusedPayees as $payee) {
                                // Delete the payee config first
                                if ($payee->config) {
                                    $payee->config->delete();
                                }
                                // Delete the account entity
                                $payee->delete();
                            }
                            DB::commit();
                            $this->info("Deleted " . count($unusedPayees) . " unused payees");
                        } catch (\Exception $e) {
                            DB::rollBack();
                            $this->error("Error deleting payees: " . $e->getMessage());
                            return 1;
                        }
                    } else {
                        $this->info('Cancelled.');
                    }
                }
            }
        }

        // Summary report
        $this->newLine();
        $this->info('=== Summary ===');
        
        // Check current state
        $totalOrphaned = DB::table('transaction_details_standard as tds')
            ->leftJoin('transactions as t', function($join) {
                $join->on('t.config_id', '=', 'tds.id')
                     ->where('t.config_type', '=', 'standard');
            })
            ->whereNull('t.id')
            ->count();
        
        $totalPayees = AccountEntity::where('config_type', 'payee')->count();
        
        $this->table(
            ['Metric', 'Count'],
            [
                ['Orphaned transaction details remaining', $totalOrphaned],
                ['Total payees', $totalPayees],
            ]
        );

        if ($totalOrphaned > 0 && $dryRun) {
            $this->newLine();
            $this->comment('Run without --dry-run to actually delete the orphaned records');
        }

        return 0;
    }
}
