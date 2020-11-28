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
        $this->seedSql();
    }

    private function seedRandom() {
        factory(InvestmentGroup::class, 5)->create();
    }

    private function seedFixed() {
        InvestmentGroup::create([
            'name' => 'Részvény'
        ]);
        InvestmentGroup::create([
            'name' => 'Befektetési alap'
        ]);
    }

    private function seedSql() {
        Eloquent::unguard();
        $path = 'storage/fin_migrations/investment_groups.sql';
        DB::unprepared(file_get_contents($path));
    }
}
