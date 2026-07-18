<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

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
        $anchor = Carbon::parse((string) config('demo.seed_anchor_date'));
        $today = Carbon::today();

        return ($today->year - $anchor->year) * 12 + ($today->month - $anchor->month);
    }

    /**
     * Shift every configured date column by the given number of months (negative to shift back).
     *
     * config('demo.seed_date_shift_columns') is keyed by table name, each entry shaped as
     * array{columns: list<string>, scope: array<string, mixed>|null} - columns are the date
     * columns to shift, scope is an optional where()-clause array restricting which rows are shifted.
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
        // behaved (mysqldump wraps dumps in SET FOREIGN_KEY_CHECKS=0 by default). The whole load
        // is also wrapped in a transaction, so app:sandbox:reset-database can roll back cleanly
        // if a statement fails partway through, instead of leaving a half-loaded database.
        $sql = "START TRANSACTION;\nSET FOREIGN_KEY_CHECKS=0;\n\n";

        foreach (self::TABLES as $table) {
            $columns = array_values(array_diff(Schema::getColumnListing($table), ['created_at', 'updated_at']));
            $columnList = implode(',', array_map(fn (string $column): string => "`{$column}`", $columns));

            $query = DB::table($table)
                ->when(
                    in_array($table, config('demo.seed_only_tables'), true),
                    fn ($query) => $query->whereNull('created_at')
                );

            foreach ($this->orderColumns($table, $columns) as $orderColumn) {
                $query->orderBy($orderColumn);
            }

            $rows = $query->get($columns);

            if ($rows->isEmpty()) {
                continue;
            }

            $tuples = $rows->map(function ($row) use ($columns, $pdo): string {
                $values = implode(',', array_map(
                    function (string $column) use ($row, $pdo) {
                        $value = $row->{$column};

                        if ($value === null) {
                            return 'NULL';
                        }

                        if (is_bool($value)) {
                            return $value ? '1' : '0';
                        }

                        return $pdo->quote((string) $value);
                    },
                    $columns
                ));

                return "({$values})";
            });

            $sql .= "-- {$table}\n";
            $sql .= "INSERT INTO `{$table}` ({$columnList}) VALUES\n";
            $sql .= $tuples->implode(",\n") . ";\n\n";
        }

        $sql .= "SET FOREIGN_KEY_CHECKS=1;\nCOMMIT;\n";

        $temporaryPath = dirname($path) . '/.' . basename($path) . '.' . uniqid('', true) . '.tmp';
        $bytesWritten = file_put_contents($temporaryPath, $sql);

        if ($bytesWritten === false || $bytesWritten !== mb_strlen($sql, '8bit')) {
            @unlink($temporaryPath);

            throw new RuntimeException("Failed to write demo data dump to {$temporaryPath}.");
        }

        if (! rename($temporaryPath, $path)) {
            @unlink($temporaryPath);

            throw new RuntimeException("Failed to move demo data dump into place at {$path}.");
        }
    }

    /**
     * Columns to sort a table's export by, for a stable/diffable row order: the table's primary
     * key if it has one, otherwise every column (covers plain pivot tables like
     * account_entity_category_preference, which has no primary key at all).
     *
     * @param  list<string>  $fallbackColumns
     * @return list<string>
     */
    private function orderColumns(string $table, array $fallbackColumns): array
    {
        foreach (Schema::getIndexes($table) as $index) {
            if ($index['primary']) {
                return $index['columns'];
            }
        }

        return $fallbackColumns;
    }

    /**
     * Shift seed dates back to the anchor baseline, dump to the given path, then restore
     * the live dates. The whole operation runs inside a database transaction that is always
     * rolled back - the shifted dates are never actually committed, so concurrent readers never
     * see them and a killed process can never leave the database in a shifted state. The dump
     * file itself is unaffected by the rollback, since it is a filesystem write.
     */
    public function export(string $path): void
    {
        DB::beginTransaction();

        try {
            $months = $this->diffMonths();

            $this->shiftDates(-$months);
            $this->dumpTo($path);
            $this->shiftDates($months);
        } finally {
            DB::rollBack();
        }
    }
}
