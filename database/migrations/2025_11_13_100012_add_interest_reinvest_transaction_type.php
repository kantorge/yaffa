<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add Interest ReInvest transaction type (ID 13)
        // This type creates an interest yield and then automatically adds shares
        // at a price of 1 for the quantity equal to the cashflow value (dividend)
        // The cashflow is: dividend - tax - commission
        // The quantity effect is: +quantity (shares added)
        // amount_multiplier is NULL because the shares are added at net zero cost (dividend = price * quantity)
        DB::table('transaction_types')->insert([
            'id' => 13,
            'name' => 'Interest ReInvest',
            'type' => 'investment',
            'amount_multiplier' => null,
            'quantity_multiplier' => 1,
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('transaction_types')->where('id', 13)->delete();
    }
};
