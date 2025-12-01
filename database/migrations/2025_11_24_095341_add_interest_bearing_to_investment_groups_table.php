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
            $table->boolean('generates_interest')->default(false)->after('name')->comment('Whether investments in this group generate interest');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('investment_groups', function (Blueprint $table) {
            $table->dropColumn('generates_interest');
        });
    }
};
