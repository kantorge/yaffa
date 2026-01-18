<?php

namespace App\Console\Commands;

use App\Models\Account;
use App\Models\AccountEntity;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Exception;

class ImportMoneyhubAccountMapping extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'yaffa:import-moneyhub-mapping 
                            {file : Path to the CSV mapping file}
                            {--user= : User ID to apply mappings to}
                            {--dry-run : Show what would be updated without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import Moneyhub account name mappings to YAFFA account aliases';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $filePath = $this->argument('file');
        $userId = $this->option('user') ?? 1;
        $dryRun = $this->option('dry-run');

        if (! file_exists($filePath)) {
            $this->error("File not found: {$filePath}");
            return 1;
        }

        $user = User::find($userId);
        if (! $user) {
            $this->error("User not found with ID: {$userId}");
            return 1;
        }

        $this->info("Processing Moneyhub account mappings for user: {$user->name}");
        $this->newLine();

        // Parse CSV
        $mappings = $this->parseCsv($filePath);

        if (empty($mappings)) {
            $this->error('No valid mappings found in CSV');
            return 1;
        }

        $this->info("Found " . count($mappings) . " mappings to process");
        $this->newLine();

        $updated = 0;
        $skipped = 0;
        $notFound = 0;
        $errors = [];

        DB::beginTransaction();

        try {
            foreach ($mappings as $moneyhubName => $jaffaName) {
                // Skip rows marked as SKIP or blank
                if (empty($jaffaName) || mb_strtoupper($jaffaName) === 'SKIP' || $jaffaName === 'Grand Total') {
                    $this->line("  <fg=yellow>⊘</> Skipping: {$moneyhubName}");
                    $skipped++;
                    continue;
                }

                // Find the YAFFA account by name
                $accountEntity = $this->findAccount($user, $jaffaName);

                if (! $accountEntity) {
                    $this->line("  <fg=red>✗</> Account not found: '{$jaffaName}' for Moneyhub '{$moneyhubName}'");
                    $notFound++;
                    $errors[] = [
                        'moneyhub' => $moneyhubName,
                        'jaffa' => $jaffaName,
                        'error' => 'Account not found',
                    ];
                    continue;
                }

                // Check if alias already exists on the AccountEntity (not the config)
                $currentAliases = $accountEntity->alias ? array_map('trim', explode("\n", $accountEntity->alias)) : [];

                if (in_array($moneyhubName, $currentAliases)) {
                    $this->line("  <fg=gray>○</> Already mapped: {$moneyhubName} → {$jaffaName}");
                    continue;
                }

                // Add the Moneyhub name to aliases
                $currentAliases[] = $moneyhubName;
                $newAliases = implode("\n", array_unique($currentAliases));

                if (! $dryRun) {
                    $accountEntity->alias = $newAliases;
                    $accountEntity->save();
                }

                $this->line("  <fg=green>✓</> Mapped: {$moneyhubName} → {$jaffaName}");
                $updated++;
            }

            if ($dryRun) {
                DB::rollBack();
                $this->newLine();
                $this->warn('DRY RUN: No changes were saved to the database');
            } else {
                DB::commit();
            }

            // Summary
            $this->newLine();
            $this->info('=== Summary ===');
            $this->table(
                ['Result', 'Count'],
                [
                    ['<fg=green>Updated</>', $updated],
                    ['<fg=yellow>Skipped (SKIP)</>', $skipped],
                    ['<fg=red>Not Found</>', $notFound],
                    ['Total', $updated + $skipped + $notFound],
                ]
            );

            if (! empty($errors)) {
                $this->newLine();
                $this->error('=== Accounts Not Found ===');
                $this->table(
                    ['Moneyhub Name', 'YAFFA Name Expected'],
                    array_map(fn ($e) => [$e['moneyhub'], $e['jaffa']], $errors)
                );

                $this->newLine();
                $this->comment('Tip: Check account names for typos or create missing accounts first.');
            }

            return 0;
        } catch (Exception $e) {
            DB::rollBack();
            $this->error("Error: " . $e->getMessage());
            return 1;
        }
    }

    /**
     * Parse the CSV file and return mappings
     */
    private function parseCsv(string $filePath): array
    {
        $mappings = [];

        if (($handle = fopen($filePath, 'r')) !== false) {
            $headers = fgetcsv($handle);

            while (($row = fgetcsv($handle)) !== false) {
                if (count($row) >= 2) {
                    $moneyhubName = mb_trim($row[0]);
                    $jaffaName = mb_trim($row[1]);

                    // Skip empty rows and header rows
                    if (empty($moneyhubName) || $moneyhubName === 'MoneyHub') {
                        continue;
                    }

                    $mappings[$moneyhubName] = $jaffaName;
                }
            }

            fclose($handle);
        }

        return $mappings;
    }

    /**
     * Find account by name (supports both Account and Investment entities)
     */
    private function findAccount(User $user, string $name): ?AccountEntity
    {
        // Simply search by name on AccountEntity - works for both accounts and investments
        $accountEntity = AccountEntity::where('user_id', $user->id)
            ->where('name', $name)
            ->first();

        if ($accountEntity) {
            $accountEntity->load('config');
            return $accountEntity;
        }

        return null;
    }
}
