<?php

use App\Models\AccountGroup;
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
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('opening_balance')->default(0);
            $table->foreignIdFor(AccountGroup::class)->constrained()->restrictOnDelete();
            $table->foreignIdFor(Currency::class)->constrained()->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     *
     */
    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
