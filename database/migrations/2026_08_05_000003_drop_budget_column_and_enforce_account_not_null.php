<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Must run after 2026_08_05_000002 (the transforming migration), which guarantees no
 * schedule=false, budget=true transactions remain and that every standard transaction has real
 * accounts on both sides. Both changes here are reversible on their own — only the data
 * transformation in 000002 is not.
 */
return new class () extends Migration {
    public function up(): void
    {
        // Guard checked first, before any DDL: MySQL DDL statements auto-commit and cannot be
        // rolled back, so if this check were interspersed with (or after) the schema changes
        // below, a failed guard would still leave a partially-applied, corrupted migration behind.
        // Guaranteed null-free by 2026_08_05_000002 (which converts or requires real accounts on
        // every remaining standard transaction). Fails loudly, per app/CLAUDE.md's migration
        // rules, if that invariant somehow doesn't hold rather than silently truncating data.
        $remainingNulls = DB::table('transaction_details_standard')
            ->where(function ($query) {
                $query->whereNull('account_from_id')
                    ->orWhereNull('account_to_id');
            })
            ->count();

        if ($remainingNulls > 0) {
            throw new RuntimeException(
                "{$remainingNulls} transaction_details_standard row(s) still have a null "
                . 'account_from_id/account_to_id. This should be impossible once '
                . '2026_08_05_000002_transform_budget_transactions_to_budgets has run successfully; '
                . 'investigate before re-running this migration.'
            );
        }

        // transactions.budget is part of the composite performance index added in
        // 2026_03_26_000001; it must be dropped and recreated without the column before the
        // column itself can be dropped. transactions_user_id_foreign requires a user_id-leading
        // index to exist at all times, and this index is the only one covering user_id, so MySQL
        // refuses a bare drop (error 1553). Build the replacement under a temporary name first,
        // drop the old one, then rename -- a covering index for the FK exists throughout.
        Schema::table('transactions', function (Blueprint $table) {
            $table->index(
                ['user_id', 'config_type', 'schedule', 'date'],
                'transactions_user_type_flags_date_index_tmp'
            );
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex('transactions_user_type_flags_date_index');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->renameIndex(
                'transactions_user_type_flags_date_index_tmp',
                'transactions_user_type_flags_date_index'
            );
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn('budget');
        });

        Schema::table('transaction_details_standard', function (Blueprint $table) {
            $table->unsignedBigInteger('account_from_id')->nullable(false)->change();
            $table->unsignedBigInteger('account_to_id')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('transaction_details_standard', function (Blueprint $table) {
            $table->unsignedBigInteger('account_from_id')->nullable()->change();
            $table->unsignedBigInteger('account_to_id')->nullable()->change();
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->boolean('budget')->default(false)->after('schedule');
        });

        // Same FK-covering-index constraint as up(): swap via a temporary index name
        // instead of dropping transactions_user_type_flags_date_index outright.
        Schema::table('transactions', function (Blueprint $table) {
            $table->index(
                ['user_id', 'config_type', 'schedule', 'budget', 'date'],
                'transactions_user_type_flags_date_index_tmp'
            );
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex('transactions_user_type_flags_date_index');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->renameIndex(
                'transactions_user_type_flags_date_index_tmp',
                'transactions_user_type_flags_date_index'
            );
        });
    }
};
