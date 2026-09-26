<?php

namespace Database\Seeders\Random;

use App\Models\Currency;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

class CurrencySeeder extends Seeder
{
    /**
     * Run the database seeds by creating random values with factory
     */
    public function run(?User $user, $count = 3): void
    {
        if ($user) {
            $users = new Collection([$user]);
        } else {
            $users = User::all();
        }

        $users->each(function ($user) use ($count) {
            // CurrencyFactory::configure() already guarantees a unique name/iso_code per user
            // against previously saved rows, so creating one at a time (not batched) is enough.
            for ($j = 0; $j < $count; $j++) {
                Currency::factory()->for($user)->create();
            }

            // Set a random currency to be default
            $currency = $user->currencies()->inRandomOrder()->first();
            $currency->base = true;
            $currency->save();
        });
    }
}
