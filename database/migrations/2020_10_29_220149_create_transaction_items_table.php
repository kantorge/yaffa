<?php

use App\Models\Category;
use App\Models\Transaction;
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
        Schema::create('transaction_items', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Transaction::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(Category::class)->constrained()->restrictOnDelete();
            $table->decimal('amount', 12, 4);
            $table->string('comment', 191)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     */
    public function down(): void
    {
        Schema::dropIfExists('transaction_items');
    }
};
