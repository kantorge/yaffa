<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('transaction_import_rules', function (Blueprint $table) {
            // Add merge_payee_id for the merge_payee action
            $table->foreignId('merge_payee_id')
                ->nullable()
                ->after('transfer_account_id')
                ->constrained('account_entities')
                ->nullOnDelete();
            
            // Add flag to append original payee name to transaction comment
            $table->boolean('append_original_to_comment')
                ->default(false)
                ->after('merge_payee_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transaction_import_rules', function (Blueprint $table) {
            $table->dropForeign(['merge_payee_id']);
            $table->dropColumn(['merge_payee_id', 'append_original_to_comment']);
        });
    }
};
