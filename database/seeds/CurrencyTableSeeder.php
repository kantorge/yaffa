<?php

use App\Currency;
use Illuminate\Database\Seeder;

class CurrencyTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        /* random strings */
        //factory(Currency::class, 5)->create();

        /* specific values */
        Currency::create([
            'name' => 'Forint',
            'iso_code' => 'HUF',
            'num_digits' => 0,
            'suffix' => 'Ft',
            'base' => true,
            'auto_update' => false,
        ]);
        Currency::create([
            'name' => 'US Dollar',
            'iso_code' => 'USD',
            'num_digits' => 2,
            'suffix' => '$',
            'base' => null,
            'auto_update' => true,
        ]);
        Currency::create([
            'name' => 'Euro',
            'iso_code' => 'EUR',
            'num_digits' => 2,
            'suffix' => '€',
            'base' => null,
            'auto_update' => true,
        ]);

    }
}
