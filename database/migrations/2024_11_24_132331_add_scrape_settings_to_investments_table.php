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
        Schema::table('investments', function (Blueprint $table) {
            /**
             * Ideally, these optional settings could be stored in a separate table, for a cleaner database structure.
             * Given the low number of investments, for now, it is acceptable to store them in the investments table.
             */
            $table->string('scrape_url')->nullable()->after('currency_id');
            $table->string('scrape_selector')->nullable()->after('scrape_url');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('investments', function (Blueprint $table) {
            $table->dropColumn('scrape_url');
            $table->dropColumn('scrape_selector');
        });
    }
};
