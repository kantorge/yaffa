<?php

use App\Investment;
use App\InvestmentGroup;
use App\Currency;

use Illuminate\Database\Seeder;

class InvestmentTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $this->seedSql();
    }

    private function seedRandom() {
        //TODO
    }

    private function seedFixed() {
        $investment = Investment::create(
            [
                'name' => 'Magyar Telekom',
                'active' => 1,
                'symbol' => 'MTEL',
                'investment_group_id' => InvestmentGroup::where('name', 'Stock')->pluck('id')->first(),
                'currency_id' => Currency::where('iso_code', 'HUF')->pluck('id')->first(),
            ]
        );

        $investment = Investment::create(
            [
                'name' => 'Disney',
                'active' => 1,
                'symbol' => 'DIS',
                'investment_group_id' => InvestmentGroup::where('name', 'Stock')->pluck('id')->first(),
                'currency_id' => Currency::where('iso_code', 'USD')->pluck('id')->first(),
                'investment_price_provider_id' => 1, //TODO: kell dinamikusnak lennie?
            ]
        );

        $investment = Investment::create(
            [
                'name' => 'Euro befektetés',
                'active' => 1,
                'symbol' => 'E',
                'investment_group_id' => InvestmentGroup::where('name', 'Mutual fund')->pluck('id')->first(),
                'currency_id' => Currency::where('iso_code', 'EUR')->pluck('id')->first(),
            ]
        );
    }

    private function seedSql() {
        Eloquent::unguard();
        $path = 'storage/fin_migrations/investments.sql';
        DB::unprepared(file_get_contents($path));
    }
}
