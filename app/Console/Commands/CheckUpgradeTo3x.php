<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CheckUpgradeTo3x extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:upgrade:check-3x';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check for known data blockers before upgrading YAFFA from 2.x to 3.x';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Checking database for known YAFFA 2.x -> 3.x upgrade blockers...');

        $issues = [];

        $unsupportedLegacyTransactionTypes = $this->checkUnsupportedLegacyTransactionTypes();

        if ($unsupportedLegacyTransactionTypes !== null) {
            $issues[] = $unsupportedLegacyTransactionTypes;
        }

        foreach ($this->checkNegativeUnsignedCandidates() as $issue) {
            $issues[] = $issue;
        }

        if ($issues === []) {
            $this->newLine();
            $this->info('No known YAFFA 2.x -> 3.x upgrade blockers were detected.');
            $this->line('This check is read-only and only validates known data issues.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->error('Upgrade preflight check failed. Resolve the following issues before upgrading to YAFFA 3.x:');

        foreach ($issues as $issue) {
            $this->line(sprintf('- %s', $issue));
        }

        return self::FAILURE;
    }

    private function checkUnsupportedLegacyTransactionTypes(): ?string
    {
        if (! Schema::hasTable('transactions') || ! Schema::hasColumn('transactions', 'transaction_type_id')) {
            return null;
        }

        $unsupportedTransactionTypes = [9, 10];

        $query = DB::table('transactions')
            ->whereIn('transaction_type_id', $unsupportedTransactionTypes);

        $count = (clone $query)->count();

        if ($count === 0) {
            return null;
        }

        $exampleIds = (clone $query)
            ->orderBy('id')
            ->limit(10)
            ->pluck('id')
            ->implode(', ');

        return sprintf(
            'Unsupported legacy transaction_type_id values (9 or 10) found in %d transaction(s). Example transaction IDs: %s.',
            $count,
            $exampleIds !== '' ? $exampleIds : 'n/a'
        );
    }

    /**
     * @return array<int, string>
     */
    private function checkNegativeUnsignedCandidates(): array
    {
        $targetColumns = [
            ['table' => 'currency_rates', 'column' => 'rate'],
            ['table' => 'investment_prices', 'column' => 'price'],
            ['table' => 'transaction_details_investment', 'column' => 'price'],
            ['table' => 'transaction_details_investment', 'column' => 'quantity'],
            ['table' => 'transaction_details_investment', 'column' => 'commission'],
            ['table' => 'transaction_details_investment', 'column' => 'tax'],
            ['table' => 'transaction_details_investment', 'column' => 'dividend'],
            ['table' => 'transaction_details_standard', 'column' => 'amount_from'],
            ['table' => 'transaction_details_standard', 'column' => 'amount_to'],
            ['table' => 'transaction_items', 'column' => 'amount'],
        ];

        $issues = [];

        foreach ($targetColumns as $targetColumn) {
            if (! Schema::hasTable($targetColumn['table']) || ! Schema::hasColumn($targetColumn['table'], $targetColumn['column'])) {
                continue;
            }

            $query = DB::table($targetColumn['table'])
                ->where($targetColumn['column'], '<', 0);

            $count = (clone $query)->count();

            if ($count === 0) {
                continue;
            }

            $exampleIds = (clone $query)
                ->orderBy('id')
                ->limit(10)
                ->pluck('id')
                ->implode(', ');

            $issues[] = sprintf(
                'Negative values found in %s.%s for %d row(s). Example row IDs: %s.',
                $targetColumn['table'],
                $targetColumn['column'],
                $count,
                $exampleIds !== '' ? $exampleIds : 'n/a'
            );
        }

        return $issues;
    }
}
