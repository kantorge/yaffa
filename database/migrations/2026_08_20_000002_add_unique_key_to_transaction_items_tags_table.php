<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * transaction_items_tags has no unique constraint on
 * (tag_id, transaction_item_id), so TransactionItem::tags()->attach()
 * (a plain belongsToMany with no sync()-style dedup) can attach the same
 * tag to the same item more than once, inflating tag lists/counts.
 *
 * Existing duplicates are removed first (the oldest row of each pair is kept),
 * otherwise adding the unique key would fail on an installation that has any.
 */
return new class () extends Migration {
    public function up(): void
    {
        DB::statement('
            DELETE t FROM transaction_items_tags t
            JOIN (
                SELECT MIN(id) AS keep_id, tag_id, transaction_item_id
                FROM transaction_items_tags
                GROUP BY tag_id, transaction_item_id
            ) k ON k.tag_id = t.tag_id AND k.transaction_item_id = t.transaction_item_id
            WHERE t.id <> k.keep_id
        ');

        Schema::table('transaction_items_tags', function (Blueprint $table) {
            $table->unique(['tag_id', 'transaction_item_id']);
        });

        // Left over from down(), which needs a plain tag_id index while the unique key is gone.
        // The unique key covers the foreign key again, so it is redundant here.
        if (Schema::hasIndex('transaction_items_tags', 'transaction_items_tags_tag_id_index')) {
            Schema::table('transaction_items_tags', function (Blueprint $table) {
                $table->dropIndex('transaction_items_tags_tag_id_index');
            });
        }
    }

    public function down(): void
    {
        Schema::table('transaction_items_tags', function (Blueprint $table) {
            // The unique key is the only index backing the tag_id foreign key, so MySQL refuses to
            // drop it unless a plain index takes over that role first.
            $table->index('tag_id');
            $table->dropUnique(['tag_id', 'transaction_item_id']);
        });
    }
};
