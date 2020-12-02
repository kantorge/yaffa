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

    private function seedDb()
    {
        $old = DB::connection('mysql_fin_migration')->table('account_groups')->get();

        foreach ($old as $item) {
            AccountGroup::create([
                'id' => $item->id,
                'name' => $item->name,
            ]);
        }
    }
}
