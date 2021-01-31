<?php

namespace Database\Seeders\Random;

use App\Models\Currency;
use Illuminate\Database\Seeder;

class CurrencySeeder extends Seeder
{
    /**
     * Run the database seeds by creating random values with factory
     *
     * @return void
     */
    public function run()
    {
        Currency::factory()->count(5)->create();
    }
}
