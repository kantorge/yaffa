<?php

namespace Database\Seeders\Random;

use App\Models\AccountGroup;
use Illuminate\Database\Seeder;

class AccountGroupSeeder extends Seeder
{
    /**
     * Run the database seeds by creating random values with factory
     *
     * @return void
     */
    public function run()
    {
        AccountGroup::factory()->count(5)->create();
    }
}
