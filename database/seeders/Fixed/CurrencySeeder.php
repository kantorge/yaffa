<?php

namespace Database\Seeders\Fixed;

use App\Models\Currency;
use App\Models\User;
use Illuminate\Database\Seeder;

class CurrencySeeder extends Seeder
{
    /**
     * Run the database seeds by creating pre-defined values
     */
    public function run(User $user)
    {
        Currency::factory()->create([
            'name' => 'Hungarian Forint',
            'iso_code' => 'HUF',
            'base' => null,
            'auto_update' => false,
            'user_id' => $user->id,
        ]);

        Currency::factory()->create([
            'name' => 'US Dollar',
            'iso_code' => 'USD',
            'base' => null,
            'auto_update' => true,
            'user_id' => $user->id,
        ]);

        Currency::factory()->create([
            'name' => 'Euro',
            'iso_code' => 'EUR',
            'base' => true,
            'auto_update' => true,
            'user_id' => $user->id,
        ]);
    }
}
