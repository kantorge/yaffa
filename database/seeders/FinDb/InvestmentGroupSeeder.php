<?php

namespace Database\Seeders\FinDb;

use App\Models\InvestmentGroup;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class InvestmentGroupSeeder extends Seeder
{
    /**
     * Run the database seeds by reading data from legacy system's remote DB
     *
     * @return void
     */
    public function run()
    {
        $old = DB::connection('mysql_fin_migration')->table('investment_groups')->get();

        foreach ($old as $item) {
            InvestmentGroup::create([
                'id' => $item->id,
                'name' => $item->name,
            ]);
        }
    }
}
