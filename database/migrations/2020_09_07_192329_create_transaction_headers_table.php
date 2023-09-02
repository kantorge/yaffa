<?php

use App\Models\TransactionType;
use App\Models\User;
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
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(User::class)->constrained()->cascadeOnDelete();
            $table->date('date')->nullable();
            $table->foreignIdFor(TransactionType::class)->constrained()->restrictOnDelete();
            $table->boolean('reconciled')->default('0');
            $table->boolean('schedule')->default('0');
            $table->boolean('budget')->default('0');
            $table->string('comment', 191)->nullable();
            $table->morphs('config');
            $table->timestamps();

            // Add an index for the config_type and config_id columns
            $table->index(['config_id', 'config_type']);
        });
    }

    /**
     * Reverse the migrations.
     *
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
