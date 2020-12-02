<?php

use App\TransactionType;
use Illuminate\Database\Seeder;

class TransactionTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run($extra)
    {
        switch($extra) {
            case 'random':
                $this->seedRandom();
                break;
            case 'fixed':
                $this->seedFixed();
                break;
            case 'sql':
                $this->seedSql();
                break;
            case 'db':
                $this->seedDb();
                break;
        }
    }

    public function seedFixed() {
        DB::table('transaction_types')->insert([
            array(
                'id' =>  "1",
                'name' => "withdrawal",
                'type' => "Standard",
                "amount_operator" => "minus",
                "quantity_operator" => null,
            ),
            array(
                'id' =>  "2",
                'name' => "deposit",
                'type' => "Standard",
                "amount_operator" => "plus",
                "quantity_operator" => null,
            ),
            array(
                'id' =>  "3",
                'name' => "transfer",
                'type' => "Standard",
                "amount_operator" => null,
                "quantity_operator" => null,
            ),
            array(
                'id' =>  "4",
                'name' => "Buy",
                'type' => "Investment",
                "amount_operator" => "minus",
                "quantity_operator" => "plus",
            ),
            array(
                'id' =>  "5",
                'name' => "Sell",
                'type' => "Investment",
                "amount_operator" => "plus",
                "quantity_operator" => "minus",
            ),
            array(
                'id' =>  "6",
                'name' => "Add shares",
                'type' => "Investment",
                "amount_operator" => null,
                "quantity_operator" => "plus",
            ),
            array(
                'id' =>  "7",
                'name' => "Remove shares",
                'type' => "Investment",
                "amount_operator" => null,
                "quantity_operator" => "minus",
            ),
            array(
                'id' =>  "8",
                'name' => "Dividend",
                'type' => "Investment",
                "amount_operator" => "plus",
                "quantity_operator" => null,
            ),
            array(
                'id' =>  "9",
                'name' => "S-Term Cap Gains",
                'type' => "Investment",
                "amount_operator" => "plus",
                "quantity_operator" => null,
            ),
            array(
                'id' =>  "10",
                'name' => "L-Term Cap Gains",
                'type' => "Investment",
                "amount_operator" => "plus",
                "quantity_operator" => null,
            ),
        ]);
    }

    private function seedDb()
    {
        $old = DB::connection('mysql_fin_migration')->table('transaction_types')->get();

        foreach ($old as $item) {
            TransactionType::create([
                'id' => $item->id,
                'name' => $item->name,
                'type' => $item->type,
                'amount_operator' => $item->amount_operator,
                'quantity_operator' => $item->quantity_operator,
            ]);
       }
    }
}
