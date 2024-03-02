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
        Schema::create('account_monthly_summaries', function (Blueprint $table) {
            $table->id();
            // We need to store the date of the summary, but in the practice, it'll be the first day of the month
            $table->date('date');
            $table->foreignId('user_id')
                ->constrained('users')
                // In the unlikely event that a user is deleted, we want the monthly summaries to be deleted as well
                ->cascadeOnDelete();
            $table->foreignId('account_entity_id')
                // The account entity is nullable, because we need to have generic budget entries
                ->nullable()
                ->constrained('account_entities')
                // In the unlikely event that an account entity is deleted,
                // we want the monthly summaries to be deleted as well
                ->cascadeOnDelete();
            $table->string('transaction_type');
            $table->string('data_type');
            $table->decimal('amount', 14, 4);
            // We don't need the created_at column, but we'll use the updated_at column
            $table->timestamp('updated_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('account_monthly_summaries');
    }
};
