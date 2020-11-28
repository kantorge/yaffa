<?php

use App\AccountGroup;
use Illuminate\Database\Seeder;

class AccountGroupTableSeeder extends Seeder
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

    private function seedRandom()
    {
        factory(AccountGroup::class, 5)->create();
    }

    private function seedFixed()
    {
        AccountGroup::create([
            'name' => 'Készpénz'
        ]);
        AccountGroup::create([
            'name' => 'Bankszámla'
        ]);
        AccountGroup::create([
            'name' => 'Hitelek'
        ]);
        AccountGroup::create([
            'name' => 'Befektetés'
        ]);
    }

    private function seedSql()
    {
        Eloquent::unguard();
        $path = 'storage/fin_migrations/account_groups.sql';
        DB::unprepared(file_get_contents($path));
    }
}
