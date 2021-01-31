<?php

namespace Database\Seeders\FinDb;

use App\Models\TransactionType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TransactionTypeSeeder extends Seeder
{
    /**
     * Run the database seeds by reading data from legacy system's remote DB
     *
     * @return void
     */
    public function run()
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
