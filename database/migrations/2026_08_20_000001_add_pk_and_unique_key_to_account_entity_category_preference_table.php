<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * account_entity_category_preference has no primary key and no unique
 * constraint on (account_entity_id, category_id), so duplicate preference
 * rows for the same pair are possible (AccountEntity::categoryPreference()
 * is a belongsToMany pivot with no dedicated model to guard against it).
 * A surrogate id also gives InnoDB a real clustered key instead of a hidden
 * one, which online-schema-change tools and row-based replication need.
 *
 * Existing duplicates are removed before the unique key is added (the most
 * recently inserted row of each pair wins, as the latest preference is the
 * one the user last set), otherwise the migration would fail on an
 * installation that has any.
 */
return new class () extends Migration {
    public function up(): void
    {
        // The column check makes a re-run safe after a previous attempt failed on the unique key
        if (! Schema::hasColumn('account_entity_category_preference', 'id')) {
            Schema::table('account_entity_category_preference', function (Blueprint $table) {
                $table->id()->first();
            });
        }

        DB::statement('
            DELETE t FROM account_entity_category_preference t
            JOIN (
                SELECT MAX(id) AS keep_id, account_entity_id, category_id
                FROM account_entity_category_preference
                GROUP BY account_entity_id, category_id
            ) k ON k.account_entity_id = t.account_entity_id AND k.category_id = t.category_id
            WHERE t.id <> k.keep_id
        ');

        Schema::table('account_entity_category_preference', function (Blueprint $table) {
            $table->unique(['account_entity_id', 'category_id'], 'account_entity_category_preference_unique');
        });

        // Left over from down(), which needs a plain account_entity_id index while the unique key
        // is gone. The unique key covers the foreign key again, so it is redundant here.
        if (Schema::hasIndex('account_entity_category_preference', 'account_entity_category_preference_account_entity_id_index')) {
            Schema::table('account_entity_category_preference', function (Blueprint $table) {
                $table->dropIndex('account_entity_category_preference_account_entity_id_index');
            });
        }
    }

    public function down(): void
    {
        Schema::table('account_entity_category_preference', function (Blueprint $table) {
            // The unique key is the only index backing the account_entity_id foreign key, so MySQL
            // refuses to drop it unless a plain index takes over that role first.
            $table->index('account_entity_id');
            $table->dropUnique('account_entity_category_preference_unique');
            $table->dropColumn('id');
        });
    }
};
