<?php

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
        Schema::create('transaction_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type');
            $table->string('amount_operator')->nullable();
            $table->string('quantity_operator')->nullable();
        });

        // Add transaction types
        DB::table('transaction_types')->insert(
            [
                'id' => 1,
                'name' => 'withdrawal',
                'type' => 'standard',
                'amount_operator' => 'minus',
                'quantity_operator' => null,
            ]
        );

        DB::table('transaction_types')->insert(
            [
                'id' => 2,
                'name' => 'deposit',
                'type' => 'standard',
                'amount_operator' => 'plus',
                'quantity_operator' => null,
            ]
        );

        DB::table('transaction_types')->insert(
            [
                'id' => 3,
                'name' => 'transfer',
                'type' => 'standard',
                'amount_operator' => null,
                'quantity_operator' => null,
            ]
        );

        DB::table('transaction_types')->insert(
            [
                'id' => 4,
                'name' => 'Buy',
                'type' => 'investment',
                'amount_operator' => 'minus',
                'quantity_operator' => 'plus',
            ]
        );

        DB::table('transaction_types')->insert(
            [
                'id' => 5,
                'name' => 'Sell',
                'type' => 'investment',
                'amount_operator' => 'plus',
                'quantity_operator' => 'minus',
            ]
        );

        DB::table('transaction_types')->insert(
            [
                'id' => 6,
                'name' => 'Add shares',
                'type' => 'investment',
                'amount_operator' => null,
                'quantity_operator' => 'plus',
            ]
        );

        DB::table('transaction_types')->insert(
            [
                'id' => 7,
                'name' => 'Remove shares',
                'type' => 'investment',
                'amount_operator' => null,
                'quantity_operator' => 'minus',
            ]
        );

        DB::table('transaction_types')->insert(
            [
                'id' => 8,
                'name' => 'Dividend',
                'type' => 'investment',
                'amount_operator' => 'plus',
                'quantity_operator' => null,
            ]
        );

        DB::table('transaction_types')->insert(
            [
                'id' => 9,
                'name' => 'unused',
                'type' => 'unused',
                'amount_operator' => null,
                'quantity_operator' => null,
            ]
        );

        DB::table('transaction_types')->insert(
            [
                'id' => 10,
                'name' => 'unused',
                'type' => 'unused',
                'amount_operator' => null,
                'quantity_operator' => null,
            ]
        );

        DB::table('transaction_types')->insert(
            [
                'id' => 11,
                'name' => 'Interest yield',
                'type' => 'investment',
                'amount_operator' => 'plus',
                'quantity_operator' => null,
            ]
        );
    }

    /**
     * Reverse the migrations.
     *
     */
    public function down(): void
    {
        Schema::dropIfExists('transaction_types');
    }
};
