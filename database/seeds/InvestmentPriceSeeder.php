<?php

use App\Investment;
use App\InvestmentPrice;
use Illuminate\Database\Seeder;

class InvestmentPriceSeeder extends Seeder
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

    private function seedSql() {
        Eloquent::unguard();
        $path = 'storage/fin_migrations/investment_prices.sql';
        DB::unprepared(file_get_contents($path));
    }
}
