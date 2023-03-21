<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTransactionTypesTable extends Migration
{
    /**
     * Run the migrations.
     *
     */
    public function up()
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
                'type' => 'Standard',
                'amount_operator' => 'minus',
                'quantity_operator' => null,
            ]
        );

        DB::table('transaction_types')->insert(
            [
                'id' => 2,
                'name' => 'deposit',
                'type' => 'Standard',
                'amount_operator' => 'plus',
                'quantity_operator' => null,
            ]
        );

        DB::table('transaction_types')->insert(
            [
                'id' => 3,
                'name' => 'transfer',
                'type' => 'Standard',
                'amount_operator' => null,
                'quantity_operator' => null,
            ]
        );

        DB::table('transaction_types')->insert(
            [
                'id' => 4,
                'name' => 'Buy',
                'type' => 'Investment',
                'amount_operator' => 'minus',
                'quantity_operator' => 'plus',
            ]
        );

        DB::table('transaction_types')->insert(
            [
                'id' => 5,
                'name' => 'Sell',
                'type' => 'Investment',
                'amount_operator' => 'plus',
                'quantity_operator' => 'minus',
            ]
        );

        DB::table('transaction_types')->insert(
            [
                'id' => 6,
                'name' => 'Add shares',
                'type' => 'Investment',
                'amount_operator' => null,
                'quantity_operator' => 'plus',
            ]
        );

        DB::table('transaction_types')->insert(
            [
                'id' => 7,
                'name' => 'Remove shares',
                'type' => 'Investment',
                'amount_operator' => null,
                'quantity_operator' => 'minus',
            ]
        );

        DB::table('transaction_types')->insert(
            [
                'id' => 8,
                'name' => 'Dividend',
                'type' => 'Investment',
                'amount_operator' => 'plus',
                'quantity_operator' => null,
            ]
        );

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
    }

    /**
     * Reverse the migrations.
     *
     */
    public function down()
    {
        Schema::dropIfExists('transaction_types');
    }
}
