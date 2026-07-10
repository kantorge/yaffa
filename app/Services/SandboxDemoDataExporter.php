<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Shared date-shift and dump logic for the sandbox demo data workflow, used by both
 * app:sandbox:dump-database and app:sandbox:promote-database. Keeping the shift math and
 * the table list here (rather than duplicated per command) is what keeps
 * app:sandbox:reset-database's forward shift and the dump/promote backward shift in sync.
 */
class SandboxDemoDataExporter
{
    /**
     * Tables included in the sandbox dump/promote export, in an order that satisfies foreign
     * key dependencies when the resulting file is reloaded by app:sandbox:reset-database.
     *
     * @var list<string>
     */
    private const TABLES = [
        'investments',
        'tags',
        'transaction_items_tags',
        'currencies',
        'transactions',
        'transaction_items',
        'categories',
        'flags',
        'transaction_schedules',
        'account_entity_category_preference',
        'account_groups',
        'accounts',
        'transaction_details_standard',
        'transaction_details_investment',
        'investment_groups',
        'account_entities',
        'payees',
        'received_mails',
        'investment_prices',
    ];

    /**
     * Number of whole months between config('demo.seed_anchor_date') and today, matching
     * the calculation app:sandbox:reset-database uses to shift seed dates forward.
     */
    public function diffMonths(): int
    {
        $anchor = (string) config('demo.seed_anchor_date');
        $diff = date_diff(date_create($anchor), date_create(date('Y-m-d')));

        return $diff->y * 12 + $diff->m + 1;
    }

    /**
     * Shift every configured date column by the given number of months (negative to shift back).
     */
    public function shiftDates(int $months): void
    {
        foreach (config('demo.seed_date_shift_columns') as $table => $definition) {
            $updates = [];
            foreach ($definition['columns'] as $column) {
                $updates[$column] = DB::raw("DATE_ADD({$column}, INTERVAL {$months} MONTH)");
            }

            DB::table($table)
                ->when($definition['scope'], fn ($query) => $query->where($definition['scope']))
                ->update($updates);
        }
    }

    /**
     * Dump the sandbox tables to the given file path as one multi-row INSERT per table, column
     * list stated once with each row's values on its own line below - so rows are easy to scan
     * and a diff shows exactly which rows changed, without repeating the column list on every
     * line. created_at/updated_at columns are omitted everywhere (unused by the app, and would
     * otherwise churn on every dump), and config('demo.seed_only_tables') is limited to rows
     * with created_at IS NULL - i.e. rows that came from demo.sql itself, not ones added
     * afterwards by reset-database's own Eloquent-based post-load steps (see the config comment
     * for why this reliably tells them apart).
     */
    public function dumpTo(string $path): void
    {
        $pdo = DB::connection()->getPdo();

        // Table insert order below doesn't follow strict FK dependency order, so foreign key
        // checks are disabled for the load, matching how the previous mysqldump-based export
        // behaved (mysqldump wraps dumps in SET FOREIGN_KEY_CHECKS=0 by default).
        $sql = "SET FOREIGN_KEY_CHECKS=0;\n\n";

        foreach (self::TABLES as $table) {
            $columns = array_values(array_diff(Schema::getColumnListing($table), ['created_at', 'updated_at']));
            $columnList = implode(',', array_map(fn (string $column): string => "`{$column}`", $columns));

            $query = DB::table($table)
                ->when(
                    in_array($table, config('demo.seed_only_tables'), true),
                    fn ($query) => $query->whereNull('created_at')
                );

            foreach ($this->orderColumns($table) as $orderColumn) {
                $query->orderBy($orderColumn);
            }

            $rows = $query->get($columns);

            if ($rows->isEmpty()) {
                continue;
            }

            $tuples = $rows->map(function ($row) use ($columns, $pdo): string {
                $values = implode(',', array_map(
                    fn (string $column) => $row->{$column} === null ? 'NULL' : $pdo->quote((string) $row->{$column}),
                    $columns
                ));

                return "({$values})";
            });

            $sql .= "-- {$table}\n";
            $sql .= "INSERT INTO `{$table}` ({$columnList}) VALUES\n";
            $sql .= $tuples->implode(",\n") . ";\n\n";
        }

        $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";

        file_put_contents($path, $sql);
    }

    /**
     * Columns to sort a table's export by, for a stable/diffable row order: the table's primary
     * key if it has one, otherwise every column (covers plain pivot tables like
     * account_entity_category_preference, which has no primary key at all).
     *
     * @return list<string>
     */
    private function orderColumns(string $table): array
    {
        foreach (Schema::getIndexes($table) as $index) {
            if ($index['primary']) {
                return $index['columns'];
            }
        }

        return Schema::getColumnListing($table);
    }

    /**
     * Shift seed dates back to the anchor baseline, dump to the given path, then restore
     * the live dates. The restore always runs, even if the dump itself fails.
     */
    public function export(string $path): void
    {
        $months = $this->diffMonths();

        $this->shiftDates(-$months);

        try {
            $this->dumpTo($path);
        } finally {
            $this->shiftDates($months);
        }
    }
}
