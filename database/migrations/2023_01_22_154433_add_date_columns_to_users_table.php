<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     *
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->addColumn('date', 'start_date')->after('locale')->default(DB::raw('(date_sub(CURRENT_TIMESTAMP, INTERVAL 30 DAY))'));
            $table->addColumn('date', 'end_date')->after('start_date')->default(DB::raw('(date_add(CURRENT_TIMESTAMP, INTERVAL 50 YEAR))'));
        });
    }

    /**
     * Reverse the migrations.
     *
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['start_date', 'end_date']);
        });
    }
};
