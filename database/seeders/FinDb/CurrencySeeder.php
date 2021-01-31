<?php

namespace Database\Seeders\FinDb;

use App\Models\Currency;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CurrencySeeder extends Seeder
{
    /**
     * Run the database seeds by reading data from legacy system's remote DB
     *
     * @return void
     */
    public function run()
    {
        $old = DB::connection('mysql_fin_migration')->table('currencies')->get();

        foreach ($old as $item) {
            Currency::create([
                'id' => $item->id,
                'name' => $item->name,
                'iso_code' => $item->iso_code,
                'num_digits' => $item->num_digits,
                'suffix' => $item->suffix,
                'base' => $item->base,
                'auto_update' => $item->auto_update,
            ]);
        }
    }
}
