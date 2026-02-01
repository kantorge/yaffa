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
        Schema::table('accounts', function (Blueprint $table) {
            $table->decimal('manual_balance', 30, 10)->nullable()->after('opening_balance');
            $table->decimal('manual_interest_rate', 10, 4)->nullable()->after('manual_balance');
            $table->decimal('manual_trend', 10, 4)->nullable()->after('manual_interest_rate');
        });

        Schema::table('investments', function (Blueprint $table) {
            $table->decimal('manual_balance', 30, 10)->nullable()->after('comment');
            $table->decimal('manual_trend', 10, 4)->nullable()->after('manual_balance');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropColumn(['manual_balance', 'manual_interest_rate', 'manual_trend']);
        });

        Schema::table('investments', function (Blueprint $table) {
            $table->dropColumn(['manual_balance', 'manual_trend']);
        });
    }
};
