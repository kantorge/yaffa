<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * transaction_items_tags has no unique constraint on
 * (tag_id, transaction_item_id), so TransactionItem::tags()->attach()
 * (a plain belongsToMany with no sync()-style dedup) can attach the same
 * tag to the same item more than once, inflating tag lists/counts.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::table('transaction_items_tags', function (Blueprint $table) {
            $table->unique(['tag_id', 'transaction_item_id']);
        });
    }

    public function down(): void
    {
        Schema::table('transaction_items_tags', function (Blueprint $table) {
            $table->dropUnique(['tag_id', 'transaction_item_id']);
        });
    }
};
