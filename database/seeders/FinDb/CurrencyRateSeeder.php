<?php

namespace Database\Seeders\FinDb;

use App\Models\CurrencyRate;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CurrencyRateSeeder extends Seeder
{
    /**
     * Run the database seeds by reading data from legacy system's remote DB
     *
     * @return void
     */
    public function run()
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
