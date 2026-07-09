<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The `transactions` table carries two composite indexes covering the same
 * two columns in opposite order: (config_type, config_id) and
 * (config_id, config_type). Every query in the codebase that filters by
 * config_id also filters by config_type (via the byType() scope), so the
 * (config_type, config_id) index already serves those lookups via its
 * leftmost prefix. The (config_id, config_type) index is redundant and only
 * adds write overhead on the most-written table in the schema.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex('transactions_config_id_config_type_index');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->index(
                ['config_id', 'config_type'],
                'transactions_config_id_config_type_index'
            );
        });
    }
};
