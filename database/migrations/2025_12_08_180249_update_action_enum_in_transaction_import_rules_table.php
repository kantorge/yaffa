<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // MySQL doesn't support direct ALTER ENUM, so we need to use raw SQL
        DB::statement("ALTER TABLE transaction_import_rules MODIFY COLUMN action ENUM('convert_to_transfer', 'skip', 'modify', 'merge_payee') NOT NULL DEFAULT 'convert_to_transfer'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert back to original enum values
        DB::statement("ALTER TABLE transaction_import_rules MODIFY COLUMN action ENUM('convert_to_transfer', 'skip', 'modify') NOT NULL DEFAULT 'convert_to_transfer'");
    }
};
