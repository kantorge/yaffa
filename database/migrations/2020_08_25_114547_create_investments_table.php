<?php

use App\Models\Currency;
use App\Models\InvestmentGroup;
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
        Schema::create('investments', function (Blueprint $table) {
            $table->id();
            $table->string('symbol', 191)->nullable();
            $table->string('isin', 12)->nullable();
            $table->boolean('auto_update')->default('0');
            $table->string('investment_price_provider', 191)->nullable();
            $table->foreignIdFor(InvestmentGroup::class)->constrained();
            $table->foreignIdFor(Currency::class)->constrained();

            // TODO: make some identifiers unique for each user. This is enforced only by the app at the moment.
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('investments');
    }
};
