<?php

use App\Models\Investment;
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
        Schema::create('transaction_details_investment', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('account_entities')->restrictOnDelete();
            $table->foreignIdFor(Investment::class)->constrained()->restrictOnDelete();
            $table->decimal('price', 10, 4)->nullable();
            $table->decimal('quantity', 14, 4)->nullable();
            $table->decimal('commission', 14, 4)->nullable();
            $table->decimal('tax', 14, 4)->nullable();
            $table->decimal('dividend', 12, 4)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     */
    public function down(): void
    {
        Schema::dropIfExists('transaction_details_investment');
    }
};
