<?php

use App\Currency;
use App\CurrencyRate;
use Carbon\CarbonPeriod;
use Illuminate\Database\Seeder;

class CurrencyRateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $currencies = Currency::all();

        $baseCurrency = $currencies->where('base',1)->first();

        $currencies->except($baseCurrency->id)->each(function($currency) use ($baseCurrency) {
            $newValue  = rand(1,300);
            $period = CarbonPeriod::create('-1 year', '1 day', 'yesterday');
            foreach ($period as $key => $date) {
                $newValue = $newValue * (rand(900 , 1100) / 1000);
                $currencyRate = CurrencyRate::create([
                    'date' => $date,
                    'rate' => $newValue,
                    'from_id' => $currency->id,
                    'to_id' => $baseCurrency->id,
                ]);
            }
        });
    }
}
