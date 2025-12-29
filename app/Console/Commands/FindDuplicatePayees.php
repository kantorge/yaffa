<?php

namespace App\Console\Commands;

use App\Models\AccountEntity;
use App\Models\Transaction;
use App\Models\TransactionType;
use App\Services\NlpService;
use Illuminate\Console\Command;

class FindDuplicatePayees extends Command
{
    protected $signature = 'payees:find-duplicates
                            {--threshold=0.85 : Similarity threshold (0.0-1.0)}
                            {--user= : Specific user ID to check}
                            {--merge : Actually merge the duplicates (default: dry-run)}';

    protected $description = 'Find and optionally merge duplicate payees using NLP semantic similarity';

    private NlpService $nlpService;

    public function __construct(NlpService $nlpService)
    {
        parent::__construct();
        $this->nlpService = $nlpService;
    }

    public function handle(): int
    {
        if (!$this->nlpService->isAvailable()) {
            $this->error('NLP service is not available. Make sure the nlp-service container is running.');
            $this->info('Start it with: docker-compose up -d nlp-service');
            return self::FAILURE;
        }

        $threshold = (float) $this->option('threshold');
        $userId = $this->option('user');
        $shouldMerge = $this->option('merge');

        if (!$shouldMerge) {
            $this->info('Running in DRY-RUN mode. Use --merge to actually merge duplicates.');
        }

        // Get payees to check (exclude accounts and investments)
        $query = AccountEntity::payees()
            ->whereNotNull('name')
            ->where('name', '!=', '');

        if ($userId) {
            $query->where('user_id', $userId);
        }

        $payees = $query->get(['id', 'name', 'user_id'])
            ->map(fn($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'user_id' => $p->user_id
            ])
            ->toArray();

        if (empty($payees)) {
            $this->info('No payees found to check.');
            return self::SUCCESS;
        }

        $payeeCount = count($payees);
        $this->info("Analyzing {$payeeCount} payees with threshold {$threshold}...");

        $result = $this->nlpService->findDuplicates($payees, $threshold);

        if (!$result) {
            $this->error('Failed to get results from NLP service.');
            return self::FAILURE;
        }

        $duplicateGroups = $result['duplicate_groups'] ?? [];
        $totalDuplicates = $result['total_duplicates_found'] ?? 0;

        if ($totalDuplicates === 0) {
            $this->info('No duplicate payees found!');
            return self::SUCCESS;
        }

        $this->info("Found {$totalDuplicates} duplicate group(s):");
        $this->newLine();

        foreach ($duplicateGroups as $group) {
            $primary = $group['primary'];
            $duplicates = $group['duplicates'];

            $this->line("Primary: <fg=green>{$primary['name']}</> (ID: {$primary['id']})");
            
            foreach ($duplicates as $duplicate) {
                $similarity = $duplicate['similarity'];
                $this->line("  → <fg=yellow>{$duplicate['name']}</> (ID: {$duplicate['id']}) - Similarity: {$similarity}");
            }

            if ($shouldMerge) {
                if ($this->confirm("Merge these payees into {$primary['name']}?", true)) {
                    $this->mergePayees($primary['id'], array_column($duplicates, 'id'));
                    $this->info("✓ Merged successfully");
                } else {
                    $this->info("Skipped");
                }
            }

            $this->newLine();
        }

        if (!$shouldMerge) {
            $this->info('To actually merge these duplicates, run with --merge flag');
        }

        return self::SUCCESS;
    }

    /**
     * Merge duplicate payees into the primary one
     */
    private function mergePayees(int $primaryId, array $duplicateIds): void
    {
        $primary = AccountEntity::find($primaryId);

        if (!$primary) {
            $this->error("Primary payee {$primaryId} not found");
            return;
        }

        foreach ($duplicateIds as $duplicateId) {
            $duplicate = AccountEntity::find($duplicateId);

            if (!$duplicate) {
                $this->warn("Duplicate payee {$duplicateId} not found, skipping");
                continue;
            }

            // Update all transactions using this duplicate payee
            $transactionCount = Transaction::where('account_entity_id', $duplicateId)
                ->update(['account_entity_id' => $primaryId]);

            $this->line("  Moved {$transactionCount} transaction(s) from {$duplicate->name} to {$primary->name}");

            // Delete the duplicate payee
            $duplicate->delete();
        }

        // Clear NLP cache for this user
        $this->nlpService->clearUserDuplicatesCache($primary->user_id);
    }
}
