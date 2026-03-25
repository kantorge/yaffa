<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $targetColumns = [
            ['table' => 'currency_rates', 'column' => 'rate', 'definition' => 'DECIMAL(20,10) UNSIGNED NOT NULL'],
            ['table' => 'investment_prices', 'column' => 'price', 'definition' => 'DECIMAL(20,10) UNSIGNED NOT NULL'],
            ['table' => 'transaction_details_investment', 'column' => 'price', 'definition' => 'DECIMAL(10,4) UNSIGNED NULL'],
            ['table' => 'transaction_details_investment', 'column' => 'quantity', 'definition' => 'DECIMAL(14,4) UNSIGNED NULL'],
            ['table' => 'transaction_details_investment', 'column' => 'commission', 'definition' => 'DECIMAL(14,4) UNSIGNED NULL'],
            ['table' => 'transaction_details_investment', 'column' => 'tax', 'definition' => 'DECIMAL(14,4) UNSIGNED NULL'],
            ['table' => 'transaction_details_investment', 'column' => 'dividend', 'definition' => 'DECIMAL(12,4) UNSIGNED NULL'],
            ['table' => 'transaction_details_standard', 'column' => 'amount_from', 'definition' => 'DECIMAL(12,4) UNSIGNED NOT NULL'],
            ['table' => 'transaction_details_standard', 'column' => 'amount_to', 'definition' => 'DECIMAL(12,4) UNSIGNED NOT NULL'],
            ['table' => 'transaction_items', 'column' => 'amount', 'definition' => 'DECIMAL(12,4) UNSIGNED NOT NULL'],
        ];

        $negativeValueViolations = [];

        foreach ($targetColumns as $targetColumn) {
            $tableName = $targetColumn['table'];
            $columnName = $targetColumn['column'];

            if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, $columnName)) {
                continue;
            }

            $negativeValueCount = DB::table($tableName)
                ->where($columnName, '<', 0)
                ->count();

            if ($negativeValueCount > 0) {
                $negativeValueViolations[] = sprintf('%s.%s: %d', $tableName, $columnName, $negativeValueCount);
            }
        }

        if ($negativeValueViolations !== []) {
            throw new RuntimeException(
                'Negative values were found in decimal columns that must become unsigned: '
                . implode(', ', $negativeValueViolations)
                . '. Please clean up data before re-running this migration.'
            );
        }

        foreach ($targetColumns as $targetColumn) {
            $tableName = $targetColumn['table'];
            $columnName = $targetColumn['column'];

            if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, $columnName)) {
                continue;
            }

            DB::statement(sprintf(
                'ALTER TABLE `%s` MODIFY `%s` %s',
                $tableName,
                $columnName,
                $targetColumn['definition']
            ));
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $targetColumns = [
            ['table' => 'currency_rates', 'column' => 'rate', 'definition' => 'DECIMAL(20,10) NOT NULL'],
            ['table' => 'investment_prices', 'column' => 'price', 'definition' => 'DECIMAL(20,10) NOT NULL'],
            ['table' => 'transaction_details_investment', 'column' => 'price', 'definition' => 'DECIMAL(10,4) NULL'],
            ['table' => 'transaction_details_investment', 'column' => 'quantity', 'definition' => 'DECIMAL(14,4) NULL'],
            ['table' => 'transaction_details_investment', 'column' => 'commission', 'definition' => 'DECIMAL(14,4) NULL'],
            ['table' => 'transaction_details_investment', 'column' => 'tax', 'definition' => 'DECIMAL(14,4) NULL'],
            ['table' => 'transaction_details_investment', 'column' => 'dividend', 'definition' => 'DECIMAL(12,4) NULL'],
            ['table' => 'transaction_details_standard', 'column' => 'amount_from', 'definition' => 'DECIMAL(12,4) NOT NULL'],
            ['table' => 'transaction_details_standard', 'column' => 'amount_to', 'definition' => 'DECIMAL(12,4) NOT NULL'],
            ['table' => 'transaction_items', 'column' => 'amount', 'definition' => 'DECIMAL(12,4) NOT NULL'],
        ];

        foreach ($targetColumns as $targetColumn) {
            $tableName = $targetColumn['table'];
            $columnName = $targetColumn['column'];

            if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, $columnName)) {
                continue;
            }

            DB::statement(sprintf(
                'ALTER TABLE `%s` MODIFY `%s` %s',
                $tableName,
                $columnName,
                $targetColumn['definition']
            ));
        }
    }
};
