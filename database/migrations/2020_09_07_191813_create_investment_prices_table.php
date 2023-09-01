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
        Schema::create('investment_prices', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->foreignIdFor(Investment::class)->constrained()->cascadeOnDelete();
            $table->decimal('price', 10, 4);
            $table->timestamps();

            // Make a date and investment pair unique
            $table->unique(['date', 'investment_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     */
    public function down(): void
    {
        Schema::dropIfExists('investment_prices');
    }
};
