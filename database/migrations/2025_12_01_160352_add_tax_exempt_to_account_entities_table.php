<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Add tax_exempt field to mark accounts as tax-exempt (ISA, SIPP, pension, etc.)
     */
    public function up(): void
    {
        Schema::table('account_entities', function (Blueprint $table) {
            $table->boolean('tax_exempt')->default(false)->after('active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('account_entities', function (Blueprint $table) {
            $table->dropColumn('tax_exempt');
        });
    }
};
