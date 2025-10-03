<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations to allow the use of decimal values for the opening balance with 10 decimal places,
     * not breaking the existing bigInteger type.
     */
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->decimal('opening_balance', 30, 10)->change();
        });
    }

    /**
     * Reverse the migrations.
     * WARNING: This can result in data loss.
     */
    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->bigInteger('opening_balance')->default(0)->change();
        });
    }
};
