<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add alias column to accounts table
        Schema::table('accounts', function (Blueprint $table) {
            $table->text('alias')->nullable();
        });

        // Add alias column to investments table
        Schema::table('investments', function (Blueprint $table) {
            $table->text('alias')->nullable();
        });

        // Add alias column to payees table (for completeness)
        Schema::table('payees', function (Blueprint $table) {
            $table->text('alias')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropColumn('alias');
        });

        Schema::table('investments', function (Blueprint $table) {
            $table->dropColumn('alias');
        });

        Schema::table('payees', function (Blueprint $table) {
            $table->dropColumn('alias');
        });
    }
};
