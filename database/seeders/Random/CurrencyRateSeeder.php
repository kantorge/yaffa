<?php

namespace Database\Seeders\Random;

use App\Models\CurrencyRate;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class CurrencyRateSeeder extends Seeder
{
    /**
     * Run the database seeds by creating random values with factory
     */
    public function run(User $user): void
    {
        $currencies = $user->currencies()->get();

        $baseCurrency = $currencies->where('base', 1)->first();

        $currencies->except($baseCurrency->id)->each(function ($currency) use ($baseCurrency) {
            $start = Carbon::now()->subYear();
            $end = Carbon::yesterday();
            $newValue = rand(1, 300);

            // Insert in chunks to avoid memory overuse
            $chunkSize = 100;
            $buffer = [];

            while ($start->lte($end)) {
                $newValue = $newValue * (rand(900, 1100) / 1000);
                $buffer[] = [
                    'date' => $start->copy(),
                    'rate' => $newValue,
                    'from_id' => $currency->id,
                    'to_id' => $baseCurrency->id,
                ];

                if (count($buffer) >= $chunkSize) {
                    CurrencyRate::insert($buffer);
                    $buffer = [];
                }

                $start->addDay();
            }

            if (!empty($buffer)) {
                CurrencyRate::insert($buffer);
            }
        });
    }
}
