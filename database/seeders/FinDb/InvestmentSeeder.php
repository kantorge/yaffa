<?php

namespace Database\Seeders\FinDb;

use App\Models\Investment;
use Illuminate\Support\Facades\DB;

use Illuminate\Database\Seeder;

class InvestmentSeeder extends Seeder
{
    /**
     * Run the database seeds by reading data from legacy system's remote DB
     *
     * @return void
     */
    public function run()
    {
        $old = DB::connection('mysql_fin_migration')->table('investments')->get();

        foreach ($old as $item) {
            Investment::create([
                'id' => $item->id,
                'name' => $item->name,
                'symbol' => $item->symbol,
                'comment' => $item->comment,
                'active' => $item->active,
                'auto_update' => $item->auto_update,
                'currency_id' => $item->currencies_id,
                'investment_group_id' => $item->investment_groups_id,
                'investment_price_provider_id' => $item->price_provider_id,
            ]);
        }
    }
}
