<?php

namespace Database\Seeders\Random;

use App\Models\InvestmentGroup;
use Illuminate\Database\Seeder;

class InvestmentGroupSeeder extends Seeder
{
    /**
     * Run the database seeds by creating random values with factory
     *
     * @return void
     */
    public function run()
    {
        InvestmentGroup::factory()->count(5)->create();
    }
}
