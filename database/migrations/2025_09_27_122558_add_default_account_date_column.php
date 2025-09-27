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
        Schema::table('users', function (Blueprint $table) {
            $table->string('account_details_date_range')->default('none')->after('end_date');
        });

        Schema::table('accounts', function (Blueprint $table) {
            $table->string('default_date_range')->nullable()->default(null)->after('currency_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('account_details_date_range');
        });
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropColumn('default_date_range');
        });
    }
};
