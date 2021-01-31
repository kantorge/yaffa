<?php

namespace Database\Seeders\Fixed;

use App\Models\Currency;
use Illuminate\Database\Seeder;

class CurrencySeeder extends Seeder
{
    /**
     * Run the database seeds by creating pre-defined values
     *
     * @return void
     */
    public function run()
    {
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
