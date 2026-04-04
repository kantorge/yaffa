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
        Schema::table('investments', function (Blueprint $table) {
            $table->timestamp('last_price_fetch_attempted_at')->nullable()->after('provider_settings');
            $table->timestamp('last_price_fetch_succeeded_at')->nullable()->after('last_price_fetch_attempted_at');
            $table->timestamp('last_price_fetch_error_at')->nullable()->after('last_price_fetch_succeeded_at');
            $table->text('last_price_fetch_error_message')->nullable()->after('last_price_fetch_error_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('investments', function (Blueprint $table) {
            $table->dropColumn([
                'last_price_fetch_attempted_at',
                'last_price_fetch_succeeded_at',
                'last_price_fetch_error_at',
                'last_price_fetch_error_message',
            ]);
        });
    }
};
