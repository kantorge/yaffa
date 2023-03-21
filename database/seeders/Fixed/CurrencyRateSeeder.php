<?php

namespace Database\Seeders\Fixed;

use App\Models\CurrencyRate;
use App\Models\User;
use Carbon\CarbonPeriod;
use Illuminate\Database\Seeder;

class CurrencyRateSeeder extends Seeder
{
    /**
     * Run the database seeds by creating random values with factory
     */
    public function run($alias)
    {
        $userId = User::where('email', $alias . '@yaffa.cc')->first()->id;

        $currencies = User::find($userId)->currencies()->get();

        $baseCurrency = $currencies->where('base', 1)->first();

        $currencies->except($baseCurrency->id)->each(function ($currency) use ($baseCurrency) {
            $newValue = rand(1, 300);
            $period = CarbonPeriod::create('-1 year', '1 day', 'yesterday');
            foreach ($period as $date) {
                $newValue = $newValue * (rand(900, 1100) / 1000);
                CurrencyRate::create([
                    'date' => $date,
                    'rate' => $newValue,
                    'from_id' => $currency->id,
                    'to_id' => $baseCurrency->id,
                ]);
            }
        });
    }
}
