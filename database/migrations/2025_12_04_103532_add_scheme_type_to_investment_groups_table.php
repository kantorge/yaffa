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
        Schema::table('investment_groups', function (Blueprint $table) {
            $table->enum('scheme_type', ['EIS', 'SEIS', 'other'])->default('other')->after('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('investment_groups', function (Blueprint $table) {
            $table->dropColumn('scheme_type');
        });
    }
};
