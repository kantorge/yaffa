<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Opt-out flag for the budget drift review ("Review all" skips flagged categories and their children).
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->boolean('budget_suggestions_excluded')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('budget_suggestions_excluded');
        });
    }
};
