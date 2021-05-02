<?php

namespace Database\Seeders\Fixed;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TransactionTypeSeeder extends Seeder
{
    /**
     * Run the database seeds by creating pre-defined values
     *
     * @return void
     */
    public function run()
    {
        DB::table('transaction_types')->insert([
            [
                'id' =>  "1",
                'name' => "withdrawal",
                'type' => "Standard",
                "amount_operator" => "minus",
                "quantity_operator" => null,
            ],
            [
                'id' =>  "2",
                'name' => "deposit",
                'type' => "Standard",
                "amount_operator" => "plus",
                "quantity_operator" => null,
            ],
            [
                'id' =>  "3",
                'name' => "transfer",
                'type' => "Standard",
                "amount_operator" => null,
                "quantity_operator" => null,
            ],
            [
                'id' =>  "4",
                'name' => "Buy",
                'type' => "Investment",
                "amount_operator" => "minus",
                "quantity_operator" => "plus",
            ],
            [
                'id' =>  "5",
                'name' => "Sell",
                'type' => "Investment",
                "amount_operator" => "plus",
                "quantity_operator" => "minus",
            ],
            [
                'id' =>  "6",
                'name' => "Add shares",
                'type' => "Investment",
                "amount_operator" => null,
                "quantity_operator" => "plus",
            ],
            [
                'id' =>  "7",
                'name' => "Remove shares",
                'type' => "Investment",
                "amount_operator" => null,
                "quantity_operator" => "minus",
            ],
            [
                'id' =>  "8",
                'name' => "Dividend",
                'type' => "Investment",
                "amount_operator" => "plus",
                "quantity_operator" => null,
            ],
            [
                'id' =>  "9",
                'name' => "S-Term Cap Gains",
                'type' => "Investment",
                "amount_operator" => "plus",
                "quantity_operator" => null,
            ],
            [
                'id' =>  "10",
                'name' => "L-Term Cap Gains",
                'type' => "Investment",
                "amount_operator" => "plus",
                "quantity_operator" => null,
            ],
        ]);
    }
}
