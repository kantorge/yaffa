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

    private function seedDb()
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
