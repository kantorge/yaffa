<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * account_entity_category_preference has no primary key and no unique
 * constraint on (account_entity_id, category_id), so duplicate preference
 * rows for the same pair are possible (AccountEntity::categoryPreference()
 * is a belongsToMany pivot with no dedicated model to guard against it).
 * A surrogate id also gives InnoDB a real clustered key instead of a hidden
 * one, which online-schema-change tools and row-based replication need.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::table('account_entity_category_preference', function (Blueprint $table) {
            $table->id()->first();
            $table->unique(['account_entity_id', 'category_id'], 'account_entity_category_preference_unique');
        });
    }

    public function down(): void
    {
        Schema::table('account_entity_category_preference', function (Blueprint $table) {
            $table->dropUnique('account_entity_category_preference_unique');
            $table->dropColumn('id');
        });
    }
};
