<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * budgets currently only has single-column FK indexes (user_id, category_id,
 * account_id) and no composite. Every read path filters user_id + active
 * together, sometimes also category_id:
 *   BudgetApiController::index
 *   ReportApiController::budgetChart
 *   TransactionApiController::getScheduledItems (includeBudgets branch)
 *
 * Low urgency (budget rows are naturally few per user) but cheap to add now.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::table('budgets', function (Blueprint $table) {
            $table->index(['user_id', 'active', 'category_id'], 'budgets_user_active_category_index');
        });
    }

    public function down(): void
    {
        Schema::table('budgets', function (Blueprint $table) {
            $table->dropIndex('budgets_user_active_category_index');
        });
    }
};
