<?php

namespace Database\Seeders\Fixed;

use App\Models\Investment;
use App\Models\InvestmentGroup;
use App\Models\Currency;

use Illuminate\Database\Seeder;

class InvestmentSeeder extends Seeder
{
    /**
     * Run the database seeds by creating pre-defined values
     *
     * @return void
     */
    public function run()
    {
        Investment::create(
            [
                'name' => 'Magyar Telekom',
                'active' => 1,
                'symbol' => 'MTEL',
                'investment_group_id' => InvestmentGroup::where('name', 'Stock')->first()->id,
                'currency_id' => Currency::where('iso_code', 'HUF')->first()->id,
            ]
        );

        Investment::create(
            [
                'name' => 'Disney',
                'active' => 1,
                'symbol' => 'DIS',
                'investment_group_id' => InvestmentGroup::where('name', 'Stock')->pluck('id')->first(),
                'currency_id' => Currency::where('iso_code', 'USD')->pluck('id')->first(),
                'investment_price_provider_id' => 1,
            ]
        );

        Investment::create(
            [
                'name' => 'Euro investment',
                'active' => 1,
                'symbol' => 'E',
                'investment_group_id' => InvestmentGroup::where('name', 'Mutual fund')->pluck('id')->first(),
                'currency_id' => Currency::where('iso_code', 'EUR')->pluck('id')->first(),
            ]
        );
    }
}
