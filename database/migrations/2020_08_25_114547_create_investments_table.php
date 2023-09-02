<?php

use App\Models\Currency;
use App\Models\InvestmentGroup;
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
        Schema::create('investments', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(User::class)->constrained()->cascadeOnDelete();
            $table->string('name', 191)->unique();
            $table->string('symbol', 191)->unique();
            $table->string('isin', 12)->nullable();
            $table->string('comment', 191)->nullable();
            $table->boolean('active')->default('1');
            $table->boolean('auto_update')->default('0');
            $table->string('investment_price_provider', 191)->nullable();
            $table->foreignIdFor(InvestmentGroup::class)->constrained();
            $table->foreignIdFor(Currency::class)->constrained();
            $table->timestamps();

            // Make some identifieds unique for each user
            $table->unique(['name', 'user_id']);
            $table->unique(['symbol', 'user_id']);
            $table->unique(['isin', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     */
    public function down(): void
    {
        Schema::dropIfExists('investments');
    }
};
