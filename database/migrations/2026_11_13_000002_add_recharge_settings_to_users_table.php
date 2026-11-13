<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Recharge tags are opt-in per user; the backstop date hides older recharge activity from the reports.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('recharge_tags_enabled')
                ->default(false)
                ->after('auto_merge_standard_transaction_items');
            $table->date('recharge_backstop_date')
                ->nullable()
                ->after('recharge_tags_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['recharge_tags_enabled', 'recharge_backstop_date']);
        });
    }
};
