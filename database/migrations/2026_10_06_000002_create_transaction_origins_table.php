<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('transaction_origins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('transaction_id')->constrained()->cascadeOnDelete();
            // Polymorphic, but without a foreign key: `created` rows survive their source being deleted
            $table->string('origin_type', 64)->nullable();
            $table->unsignedBigInteger('origin_id')->nullable();
            $table->string('relation', 32);
            $table->text('decision_reason')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['origin_type', 'origin_id']);
            $table->index(['user_id', 'relation', 'reviewed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaction_origins');
    }
};
