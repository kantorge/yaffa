<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::dropIfExists('transaction_types');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // This migration cannot be easily reversed as it requires recreating
        // the transaction_types table and data. If you need to rollback,
        // you should restore from a backup.
        throw new \Exception('Cannot reverse dropping transaction_types table. Restore from backup if needed.');
    }
};
