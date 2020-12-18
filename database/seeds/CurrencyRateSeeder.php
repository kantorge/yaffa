<?php

use App\Currency;
use App\CurrencyRate;
use Carbon\CarbonPeriod;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CurrencyRateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run($extra)
    {
        switch($extra) {
            case 'random':
                $this->seedRandom();
                break;
            case 'fixed':
                $this->seedFixed();
                break;
            case 'sql':
                $this->seedSql();
                break;
            case 'db':
                $this->seedDb();
                break;
        }
    }

    private function seedRandom() {
        $currencies = Currency::all();

        $baseCurrency = $currencies->where('base',1)->first();

        $currencies->except($baseCurrency->id)->each(function($currency) use ($baseCurrency) {
            $newValue  = rand(1,300);
            //$period = CarbonPeriod::create('-1 year', '1 day', 'yesterday');
            $period = CarbonPeriod::create('-1 month', '1 day', 'yesterday');
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

    private function seedFixed() {
        //TODO
    }

    private function seedSql() {
        //TODO
    }

    private function seedDb()
    {
        $old = DB::connection('mysql_fin_migration')->table('currency_rates')->get();

        // creates a new progress bar based on item count
        $progressBar = $this->command->getOutput()->createProgressBar(count($old));

        // starts and displays the progress bar
        $progressBar->start();

        foreach ($old as $item) {
            CurrencyRate::create([
                'date' => $item->date,
                'rate' => $item->rate,
                'from_id' => $item->from_id,
                'to_id' => $item->to_id,
            ]);
            $progressBar->advance();
        }

        $progressBar->finish();
        $this->command->getOutput()->writeln('');
    }
}
