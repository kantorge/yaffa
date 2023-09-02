<?php

use App\Models\Currency;
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
        Schema::create('currency_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Currency::class, 'from_id')
                ->constrained('currencies')
                ->cascadeOnDelete();
            $table->foreignIdFor(Currency::class, 'to_id')
                ->constrained('currencies')
                ->cascadeOnDelete();
            $table->date('date');
            $table->decimal('rate', 8, 4);

            // Make a date and currency pair unique
            $table->unique(['date', 'from_id', 'to_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     */
    public function down(): void
    {
        Schema::dropIfExists('currency_rates');
    }
};
