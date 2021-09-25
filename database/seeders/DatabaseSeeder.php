<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database with general purpose sample data
     *
     * @return void
     */
    public function run()
    {
        $this->call(\Database\Seeders\Base\TransactionTypeSeeder::class);
    }
}
