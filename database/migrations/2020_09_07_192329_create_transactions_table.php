<?php

use App\Models\AccountEntity;
use App\Models\TransactionType;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    /**
     * Run the migrations.
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
            $table->foreignIdFor(AccountEntity::class, 'account_from_id')
                ->constrained('account_entities')
                ->restrictOnDelete();
            $table->foreignIdFor(AccountEntity::class, 'account_to_id')
                ->constrained('account_entities')
                ->restrictOnDelete();
            // This amount is used generally to capture the amount of the transaction
            $table->decimal('amount_primary', 12, 4)->nullable();
            // This amount is used to capture the amount if the transaction type is a transfer,
            // and the currency of the accounts is different
            $table->decimal('amount_secondary', 12, 4)->nullable();
            // These are investment specific fields
            $table->decimal('price', 10, 4)->nullable();
            $table->decimal('quantity', 14, 4)->nullable();
            $table->decimal('commission', 14, 4)->nullable();
            $table->decimal('tax', 14, 4)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
