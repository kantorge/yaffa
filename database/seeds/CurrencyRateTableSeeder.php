<?php

use App\Currency;
use Illuminate\Database\Seeder;

class CurrencyRateTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        /* specific values */
        CurrencyRate::create([
            'name' => 'Forint',
            'iso_code' => 'HUF',
            'num_digits' => 0,
            'suffix' => 'Ft',
            'base' => true,
            'auto_update' => false,
        ]);
    }
}
