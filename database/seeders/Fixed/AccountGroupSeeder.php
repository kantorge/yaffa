<?php

namespace Database\Seeders\Fixed;

use App\Models\AccountGroup;
use Illuminate\Database\Seeder;

class AccountGroupSeeder extends Seeder
{
    /**
     * Run the database seeds by creating pre-defined values
     *
     * @return void
     */
    public function run()
    {
        AccountGroup::create([
            'name' => 'Cash'
        ]);
        AccountGroup::create([
            'name' => 'Bank accounts'
        ]);
        AccountGroup::create([
            'name' => 'Credits and loans'
        ]);
        AccountGroup::create([
            'name' => 'Investments'
        ]);
    }
}
