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

    private function seedRandom() {
        //TODO
    }

    private function seedFixed()
    {
        $investment = Investment::create(
            [
                'name' => 'Magyar Telekom',
                'active' => 1,
                'symbol' => 'MTEL',
                'investment_group_id' => InvestmentGroup::where('name', 'Stock')->first()->id,
                'currency_id' => Currency::where('iso_code', 'HUF')->first()->id,
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

    private function seedDb()
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
