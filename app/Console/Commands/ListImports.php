<?php

namespace App\Console\Commands;

use App\Models\ImportJob;
use App\Models\Transaction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ListImports extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'yaffa:list-imports 
                            {--user= : Filter by user ID}
                            {--status= : Filter by status (queued, started, completed, failed, purged)}
                            {--limit=20 : Number of imports to show}
                            {--with-counts : Show transaction counts for each import}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List recent import jobs with their status and transaction counts';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $userId = $this->option('user');
        $status = $this->option('status');
        $limit = (int) $this->option('limit');
        $withCounts = $this->option('with-counts');

        // Build query
        $query = ImportJob::query()->orderBy('created_at', 'desc');

        if ($userId) {
            $query->where('user_id', $userId);
        }

        if ($status) {
            $query->where('status', $status);
        }

        $imports = $query->limit($limit)->get();

        if ($imports->isEmpty()) {
            $this->info('No import jobs found');
            return 0;
        }

        // Get transaction counts if requested
        $transactionCounts = [];
        if ($withCounts) {
            $this->info('Counting transactions...');
            $importIds = $imports->pluck('id')->toArray();
            $counts = DB::table('transactions')
                ->select('import_job_id', DB::raw('COUNT(*) as count'))
                ->whereIn('import_job_id', $importIds)
                ->groupBy('import_job_id')
                ->pluck('count', 'import_job_id')
                ->toArray();
            $transactionCounts = $counts;
        }

        // Prepare table data
        $headers = ['ID', 'User', 'File', 'Status', 'Rows', 'Created'];
        if ($withCounts) {
            $headers[] = 'Transactions';
        }

        $rows = $imports->map(function ($import) use ($transactionCounts, $withCounts) {
            $statusColor = match ($import->status) {
                'completed' => 'green',
                'failed' => 'red',
                'purged' => 'yellow',
                'started' => 'cyan',
                default => 'gray',
            };

            $row = [
                $import->id,
                $import->user_id,
                basename($import->file_path),
                "<fg={$statusColor}>{$import->status}</>",
                $import->processed_rows ?? 0,
                $import->created_at->format('Y-m-d H:i'),
            ];

            if ($withCounts) {
                $txnCount = $transactionCounts[$import->id] ?? 0;
                $row[] = $txnCount > 0 ? "<fg=green>{$txnCount}</>" : "<fg=gray>0</>";
            }

            return $row;
        })->toArray();

        $this->table($headers, $rows);

        // Show summary statistics
        $this->newLine();
        $this->info('=== Summary ===');
        $statusCounts = $imports->groupBy('status')->map->count();
        foreach ($statusCounts as $status => $count) {
            $this->line("  {$status}: {$count}");
        }

        if ($withCounts) {
            $totalTransactions = array_sum($transactionCounts);
            $this->line("  Total Transactions: {$totalTransactions}");
        }

        // Show helpful commands
        $this->newLine();
        $this->comment('Commands:');
        $this->line('  View details: php artisan yaffa:purge-import {id} --dry-run');
        $this->line('  Purge import: php artisan yaffa:purge-import {id}');

        return 0;
    }
}
