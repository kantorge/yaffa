<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 2026_08_20_000003 dropped single-column FK indexes subsumed by a wider
 * composite index on six tables, but missed these two — both got a covering
 * UNIQUE key added in the same batch (2026_08_20_000001, 2026_08_20_000002)
 * without dropping the now-redundant single-column FK index that's a left
 * prefix of it. MySQL still satisfies each column's FOREIGN KEY index
 * requirement via the unique key's leftmost column, so these can be dropped
 * without touching the FKs themselves.
 *
 *   table                                dropped index                                              subsumed by
 *   account_entity_category_preference   account_entity_category_preference_account_entity_id_foreign account_entity_category_preference_unique (account_entity_id, category_id)
 *   transaction_items_tags               transaction_items_tags_tag_id_foreign                         transaction_items_tags_tag_id_transaction_item_id_unique (tag_id, transaction_item_id)
 *
 * transaction_items_tags_transaction_item_id_foreign is NOT touched here — it
 * is not a left prefix of the (tag_id, transaction_item_id) unique key, so it
 * is still needed to satisfy the transaction_item_id foreign key.
 */
return new class () extends Migration {
    public function up(): void
    {
        if (Schema::hasIndex('account_entity_category_preference', 'account_entity_category_preference_account_entity_id_foreign')) {
            Schema::table('account_entity_category_preference', function (Blueprint $table) {
                $table->dropIndex('account_entity_category_preference_account_entity_id_foreign');
            });
        }

        if (Schema::hasIndex('transaction_items_tags', 'transaction_items_tags_tag_id_foreign')) {
            Schema::table('transaction_items_tags', function (Blueprint $table) {
                $table->dropIndex('transaction_items_tags_tag_id_foreign');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasIndex('account_entity_category_preference', 'account_entity_category_preference_account_entity_id_foreign')) {
            Schema::table('account_entity_category_preference', function (Blueprint $table) {
                $table->index('account_entity_id', 'account_entity_category_preference_account_entity_id_foreign');
            });
        }

        if (! Schema::hasIndex('transaction_items_tags', 'transaction_items_tags_tag_id_foreign')) {
            Schema::table('transaction_items_tags', function (Blueprint $table) {
                $table->index('tag_id', 'transaction_items_tags_tag_id_foreign');
            });
        }
    }
};
