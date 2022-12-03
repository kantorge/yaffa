<?php

namespace Database\Seeders\Random;

use App\Models\Currency;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class CurrencySeeder extends Seeder
{
    /**
     * Run the database seeds by creating random values with factory
     *
     * @return void
     */
    public function run(?User $user, $count = 3)
    {
        if ($user) {
            $users = new Collection([$user]);
        } else {
            $users = User::all();
        }

        $users->each(function ($user) use ($count) {
            // TODO: use Faker unique instead of replicating its functionality
            $maxRetries = 10000;
            $uniques = [];

            for ($j = 0; $j < $count; $j++) {
                $i = 0;

                do {
                    $res = Currency::factory()
                        ->for($user)
                        ->make();

                    $i++;

                    if ($i > $maxRetries) {
                        throw new \OverflowException(sprintf('Maximum retries of %d reached without finding a unique value', $maxRetries));
                    }
                } while (in_array(serialize($res->toArray()), $uniques, true));
                $uniques[] = serialize($res->toArray());
                $res->save();
            }

            // Set a random currency to be default
            $currency = $user->currencies()->inRandomOrder()->first();
            $currency->base = true;
            $currency->save();
        });
    }
}
