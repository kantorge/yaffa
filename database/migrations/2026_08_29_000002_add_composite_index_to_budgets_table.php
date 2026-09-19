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
        // budgets_user_active_category_index is the only index left covering the user_id FK by
        // this point - adding it caused MySQL/InnoDB to silently drop the auto-generated
        // budgets_user_id_foreign index up() implicitly relied on, since the new composite index
        // already satisfies the FK's leftmost-column requirement. Dropping it here directly fails
        // with error 1553 ("needed in a foreign key constraint"). Same build-temp-index-first,
        // drop-old-one, rename dance as 2026_08_05_000003's transactions_user_type_flags_date_index
        // swap, so a covering index for the FK exists throughout.
        //
        // Guarded with hasIndex(), same reasoning as 2026_08_20_000003/2026_08_29_000001: once
        // this down() has recreated budgets_user_id_foreign as a real (not implicitly
        // FK-generated) index, a later up() no longer causes MySQL to silently drop it again -
        // so a second up()/down() cycle (e.g. a test that resets/reapplies migrations repeatedly)
        // must not try to recreate or rename over an index that's already there.
        if (!Schema::hasIndex('budgets', 'budgets_user_id_foreign')) {
            Schema::table('budgets', function (Blueprint $table) {
                $table->index('user_id', 'budgets_user_id_foreign_tmp');
            });
        }

        Schema::table('budgets', function (Blueprint $table) {
            $table->dropIndex('budgets_user_active_category_index');
        });

        if (!Schema::hasIndex('budgets', 'budgets_user_id_foreign') && Schema::hasIndex('budgets', 'budgets_user_id_foreign_tmp')) {
            Schema::table('budgets', function (Blueprint $table) {
                $table->renameIndex('budgets_user_id_foreign_tmp', 'budgets_user_id_foreign');
            });
        }
    }
};
