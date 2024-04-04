<?php

use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Swap the values of the config_type in the transaction table
        // transaction_detail_standard -> standard
        // transaction_detail_investment -> investment
        DB::table('transactions')
            ->where('config_type', 'transaction_detail_standard')
            ->update(['config_type' => 'standard']);

        DB::table('transactions')
            ->where('config_type', 'transaction_detail_investment')
            ->update(['config_type' => 'investment']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Swap the values of the config_type in the transaction table
        // standard -> transaction_detail_standard
        // investment -> transaction_detail_investment
        DB::table('transactions')
            ->where('config_type', 'standard')
            ->update(['config_type' => 'transaction_detail_standard']);

        DB::table('transactions')
            ->where('config_type', 'investment')
            ->update(['config_type' => 'transaction_detail_investment']);
    }
};
