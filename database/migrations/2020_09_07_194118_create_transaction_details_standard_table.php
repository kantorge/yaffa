<?php

use App\Models\AccountEntity;
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
        Schema::create('transaction_details_standard', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(AccountEntity::class, 'account_from_id')
                ->constrained('account_entities')
                ->restrictOnDelete();
            $table->foreignIdFor(AccountEntity::class, 'account_to_id')
                ->constrained('account_entities')
                ->restrictOnDelete();
            $table->decimal('amount_from', 12, 4);
            $table->decimal('amount_to', 12, 4);

            // Indexes
            $table->index(['account_from_id', 'account_to_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     */
    public function down(): void
    {
        Schema::dropIfExists('transaction_details_standard');
    }
};
