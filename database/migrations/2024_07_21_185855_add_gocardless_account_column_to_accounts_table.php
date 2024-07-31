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
        Schema::table('accounts', function (Blueprint $table) {
            $table->uuid('gocardless_account_id')->nullable()->after('currency_id');
            $table->foreign('gocardless_account_id')->references('id')->on('gocardless_accounts')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropForeign(['gocardless_account_id']);
            $table->dropColumn('gocardless_account_id');
        });
    }
};
