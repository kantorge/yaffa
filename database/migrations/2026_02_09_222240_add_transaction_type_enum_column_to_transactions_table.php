<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add the new transaction_type enum column (nullable initially)
        Schema::table('transactions', function (Blueprint $table) {
            $table->enum('transaction_type', [
                'withdrawal',
                'deposit',
                'transfer',
                'buy',
                'sell',
                'add_shares',
                'remove_shares',
                'dividend',
                'unused_1',
                'unused_2',
                'interest_yield',
            ])->nullable()->after('date');
        });

        // Migrate data from transaction_type_id to transaction_type enum
        DB::statement("
            UPDATE transactions
            SET transaction_type = CASE transaction_type_id
                WHEN 1 THEN 'withdrawal'
                WHEN 2 THEN 'deposit'
                WHEN 3 THEN 'transfer'
                WHEN 4 THEN 'buy'
                WHEN 5 THEN 'sell'
                WHEN 6 THEN 'add_shares'
                WHEN 7 THEN 'remove_shares'
                WHEN 8 THEN 'dividend'
                WHEN 9 THEN 'unused_1'
                WHEN 10 THEN 'unused_2'
                WHEN 11 THEN 'interest_yield'
            END
        ");

        // Make the column non-nullable now that data is migrated
        Schema::table('transactions', function (Blueprint $table) {
            $table->enum('transaction_type', [
                'withdrawal',
                'deposit',
                'transfer',
                'buy',
                'sell',
                'add_shares',
                'remove_shares',
                'dividend',
                'unused_1',
                'unused_2',
                'interest_yield',
            ])->nullable(false)->change();
        });

        // Drop the old foreign key and column
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['transaction_type_id']);
            $table->dropColumn('transaction_type_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Recreate the transaction_type_id column
        Schema::table('transactions', function (Blueprint $table) {
            $table->unsignedBigInteger('transaction_type_id')->nullable()->after('date');
        });

        // Migrate data back from enum to ID
        DB::statement("
            UPDATE transactions
            SET transaction_type_id = CASE transaction_type
                WHEN 'withdrawal' THEN 1
                WHEN 'deposit' THEN 2
                WHEN 'transfer' THEN 3
                WHEN 'buy' THEN 4
                WHEN 'sell' THEN 5
                WHEN 'add_shares' THEN 6
                WHEN 'remove_shares' THEN 7
                WHEN 'dividend' THEN 8
                WHEN 'unused_1' THEN 9
                WHEN 'unused_2' THEN 10
                WHEN 'interest_yield' THEN 11
            END
        ");

        // Make the column non-nullable and add foreign key
        Schema::table('transactions', function (Blueprint $table) {
            $table->unsignedBigInteger('transaction_type_id')->nullable(false)->change();
            $table->foreign('transaction_type_id')->references('id')->on('transaction_types')->restrictOnDelete();
        });

        // Drop the enum column
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn('transaction_type');
        });
    }
};
