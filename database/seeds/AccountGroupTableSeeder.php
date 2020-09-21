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
        /* random strings */
        //factory(AccountGroup::class, 5)->create();

        /* specific values */
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
}
