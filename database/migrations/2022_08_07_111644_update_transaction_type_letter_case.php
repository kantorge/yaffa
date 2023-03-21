<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class () extends Migration {
    /**
     * Run the migrations.
     *
     */
    public function up()
    {
        // Update transaction_types table to make all type values small caps
        DB::table('transaction_types')->update(
            [
                'type' => DB::raw('LOWER(type)'),
            ]
        );
    }

    /**
     * Reverse the migrations.
     *
     */
    public function down()
    {
        // Update transaction_types table to make all type values upper caps
        DB::table('transaction_types')->update(
            [
                'type' => DB::raw('CONCAT(UPPER(LEFT(type,1)), LOWER(SUBSTRING(type,2, LENGTH(type))))'),
            ]
        );
    }
};
