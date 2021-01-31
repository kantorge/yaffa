<?php

namespace Database\Seeders\Fixed;

use App\Models\InvestmentGroup;
use Illuminate\Database\Seeder;

class InvestmentGroupSeeder extends Seeder
{
    /**
     * Run the database seeds by creating pre-defined values
     *
     * @return void
     */
    public function run()
    {
        InvestmentGroup::create([
            'name' => 'Stock'
        ]);
        InvestmentGroup::create([
            'name' => 'Mutual fund'
        ]);
    }
}
