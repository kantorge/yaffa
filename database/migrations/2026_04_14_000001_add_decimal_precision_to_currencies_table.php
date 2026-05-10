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
        Schema::table('currencies', function (Blueprint $table) {
            $table->unsignedInteger('generic_decimal_precision')->nullable()->after('auto_update');
            $table->unsignedInteger('detailed_decimal_precision')->nullable()->after('generic_decimal_precision');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('currencies', function (Blueprint $table) {
            $table->dropColumn(['generic_decimal_precision', 'detailed_decimal_precision']);
        });
    }
};
