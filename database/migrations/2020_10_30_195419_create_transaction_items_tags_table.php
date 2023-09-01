<?php

use App\Models\Tag;
use App\Models\TransactionItem;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     *
     */
    public function up(): void
    {
        Schema::create('transaction_items_tags', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(TransactionItem::class)->constrained();
            $table->foreignIdFor(Tag::class)->constrained()->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     *
     */
    public function down(): void
    {
        Schema::dropIfExists('transaction_items_tags');
    }
};
