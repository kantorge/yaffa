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
        Schema::create('account_balance_checkpoints', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_entity_id')->constrained('account_entities')->cascadeOnDelete();
            $table->date('checkpoint_date');
            $table->enum('checkpoint_type', ['cash', 'total', 'investment'])->default('cash');
            $table->decimal('balance', 15, 2);
            $table->text('note')->nullable();
            $table->boolean('active')->default(true);
            $table->string('source')->default('manual');
            $table->string('source_document_id')->nullable();
            $table->timestamps();

            $table->unique(['account_entity_id', 'source', 'source_document_id'], 'uniq_account_balance_checkpoint_source_document');
            $table->index('user_id');
            $table->index('account_entity_id');
            $table->index(['account_entity_id', 'checkpoint_type', 'checkpoint_date'], 'idx_account_balance_checkpoint_lookup');
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
