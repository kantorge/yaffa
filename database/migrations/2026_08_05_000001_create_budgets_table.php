<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('budgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->foreignId('category_id')
                ->constrained('categories')
                ->cascadeOnDelete();
            $table->foreignId('account_id')
                ->nullable()
                ->constrained('account_entities')
                ->cascadeOnDelete();
            // Restricted to the two standard directions at the schema level (FR-4/Non-Goals) -
            // mirrors the existing transactions.transaction_type ENUM precedent
            // (2026_01_31_000001_add_transaction_type_enum_column_to_transactions_table.php) - so
            // TransactionType::amountMultiplier() (consumed wherever a Budget's amount is
            // projected) can never see a value it has no multiplier for.
            $table->enum('transaction_type', ['withdrawal', 'deposit']);
            $table->decimal('amount', 12, 4)->unsigned();
            $table->string('comment')->nullable();
            // Recurrence shape (frequency/interval/count/end_date/by_day/by_month) is stored as a
            // single RFC 5545 RRULE string rather than one column per component - see
            // .ai/docs/specifications/budget-schedule-redesign/recurrence-rrule-storage.md and
            // App\Models\Concerns\HasRecurrenceRule, which composes/decomposes it.
            $table->text('rrule');
            $table->date('start_date');
            $table->double('inflation')->nullable();
            $table->boolean('active')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budgets');
    }
};
