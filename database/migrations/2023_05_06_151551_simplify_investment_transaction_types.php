<?php

use App\Models\TransactionType;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    /**
     * Run the migrations.
     *
     */
    public function up(): void
    {
        // Create a new investment transaction type, which is a clone of dividend
        $dividend = TransactionType::where('name', 'Dividend')->first();
        $interest = $dividend->replicate();
        $interest->name = 'Interest yield';
        $interest->id = 11;
        $interest->save();

        // Migrate transactions with type 9 and 10 to new type
        DB::table('transactions')
            ->whereIn('transaction_type_id', [9, 10])
            ->update(['transaction_type_id' => $interest->id]);

        // Delete old transaction types
        TransactionType::whereIn('id', [9, 10])->delete();
    }

    /**
     * Reverse the migrations.
     *
     */
    public function down(): void
    {
        // Restore old transaction types
        DB::table('transaction_types')->insert(
            [
                'id' => 9,
                'name' => 'S-Term Cap Gains Dist',
                'type' => 'Investment',
                'amount_operator' => 'plus',
                'quantity_operator' => null,
            ]
        );

        DB::table('transaction_types')->insert(
            [
                'id' => 10,
                'name' => 'L-Term Cap Gains Dist',
                'type' => 'Investment',
                'amount_operator' => 'plus',
                'quantity_operator' => null,
            ]
        );

        // Migrate Interest transactions to S-Term Cap Gains Dist
        // Note: this can lead to loss of information about L-Term Cap Gains Dist
        $interest = TransactionType::where('name', 'Interest yield')->first();
        DB::table('transactions')
            ->where('transaction_type_id', $interest->id)
            ->update(['transaction_type_id' => 9]);

        // Delete interest transaction type
        $interest->delete();
    }
};
