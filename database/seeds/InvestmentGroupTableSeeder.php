<?php

use App\InvestmentGroup;
use Illuminate\Database\Seeder;

class InvestmentGroupTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        /* random strings */
        //factory(InvestmentGroup::class, 5)->create();

        /* specific values */
        InvestmentGroup::create([
            'name' => 'Részvény'
        ]);
        InvestmentGroup::create([
            'name' => 'Befektetési alap'
        ]);
    }
}
