<?php

namespace App\Console\Commands;

use App\Models\ImportJob;
use App\Services\TransactionUploadService;
use Illuminate\Console\Command;

class AnalyzeImportFailures extends Command
{
    protected $signature = 'yaffa:analyze-import-failures {import_id : The import job ID to analyze}';

    protected $description = 'Analyze which accounts failed to match in an import';

    public function handle()
    {
        $importId = $this->argument('import_id');
        $import = ImportJob::find($importId);

        if (!$import) {
            $this->error("Import job {$importId} not found");
            return 1;
        }

        $this->info("Analyzing Import Job #{$importId}");
        $this->info("File: {$import->file_path}");
        $this->newLine();

        $filePath = storage_path('app/' . $import->file_path);
        if (!file_exists($filePath)) {
            $this->error("CSV file not found: {$filePath}");
            return 1;
        }

        // Parse CSV and find all unique account names
        $handle = fopen($filePath, 'r');
        $header = fgetcsv($handle);
        $accountIndex = array_search('ACCOUNT', $header);

        if ($accountIndex === false) {
            $this->error("'ACCOUNT' column not found in CSV");
            fclose($handle);
            return 1;
        }

        $accountNames = [];
        while (($row = fgetcsv($handle)) !== false) {
            if (isset($row[$accountIndex]) && !empty($row[$accountIndex])) {
                $acctName = $row[$accountIndex];
                if (!isset($accountNames[$acctName])) {
                    $accountNames[$acctName] = 0;
                }
                $accountNames[$acctName]++;
            }
        }
        fclose($handle);

        arsort($accountNames); // Sort by frequency

        $this->info("Found " . count($accountNames) . " unique account names in CSV:");
        $this->newLine();

        // Check each account name for matches
        $user = \App\Models\User::find($import->user_id);
        $service = new TransactionUploadService($user);

        $matched = [];
        $unmatched = [];

        foreach ($accountNames as $acctName => $count) {
            $accountId = $service->matchAccountByAlias($acctName);
            if ($accountId) {
                $matched[$acctName] = ['count' => $count, 'account_id' => $accountId];
            } else {
                $unmatched[$acctName] = $count;
            }
        }

        if (count($matched) > 0) {
            $this->info("✓ Matched Accounts (" . count($matched) . "):");
            foreach ($matched as $csvName => $data) {
                $account = \App\Models\AccountEntity::find($data['account_id']);
                $this->line("  \"{$csvName}\" → {$account->name} (ID: {$data['account_id']}) - {$data['count']} transactions");
            }
            $this->newLine();
        }

        if (count($unmatched) > 0) {
            $this->error("✗ Unmatched Accounts (" . count($unmatched) . "):");
            foreach ($unmatched as $csvName => $count) {
                $this->line("  \"{$csvName}\" - {$count} transactions");
            }
            $this->newLine();

            $this->warn("To fix this, you need to:");
            $this->line("1. Find or create accounts in YAFFA for each unmatched Moneyhub account");
            $this->line("2. Add the Moneyhub account name as an alias:");
            $this->line("   php artisan yaffa:add-account-alias \"YAFFA Account Name\" \"Moneyhub Account Name\"");
            $this->line("3. Or create a mapping CSV and run:");
            $this->line("   php artisan yaffa:import-moneyhub-mapping mapping.csv");
        }

        $matchedCount = array_sum(array_column($matched, 'count'));
        $unmatchedCount = array_sum($unmatched);
        $totalCount = $matchedCount + $unmatchedCount;

        $this->newLine();
        $this->info("Summary:");
        $this->line("  Total transactions in CSV: {$totalCount}");
        $this->line("  Matched: {$matchedCount} (" . round($matchedCount / $totalCount * 100, 1) . "%)");
        $this->line("  Unmatched: {$unmatchedCount} (" . round($unmatchedCount / $totalCount * 100, 1) . "%)");

        return 0;
    }
}
