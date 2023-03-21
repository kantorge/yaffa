<?php

namespace Database\Seeders\Random;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds by creating random values with factory
     */
    public function run()
    {
        User::factory()->count(2)->create();
    }
}
