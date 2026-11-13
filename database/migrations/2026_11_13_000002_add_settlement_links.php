<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * A foreign-currency account can settle into an account in the user's default currency, and a transfer
     * created together with an investment transaction remembers which investment transaction it settles.
     */
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->foreignId('settlement_account_id')
                ->nullable()
                ->constrained('account_entities')
                ->nullOnDelete();
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('settles_transaction_id')
                ->nullable()
                ->after('config_id')
                ->constrained('transactions')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('settles_transaction_id');
        });

        Schema::table('accounts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('settlement_account_id');
        });
    }
};
