<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('account_balance_checkpoints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('account_entity_id')->constrained()->onDelete('cascade');
            $table->date('checkpoint_date');
            $table->decimal('balance', 15, 2);
            $table->text('note')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            // Ensure only one active checkpoint per account per date
            $table->unique(['account_entity_id', 'checkpoint_date', 'active'], 'unique_active_checkpoint');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('account_balance_checkpoints');
    }
};
