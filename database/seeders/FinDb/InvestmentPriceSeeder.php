<?php

namespace Database\Seeders\FinDb;

use App\Models\InvestmentPrice;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class InvestmentPriceSeeder extends Seeder
{
    /**
     * Run the database seeds by reading data from legacy system's remote DB
     *
     * @return void
     */
    public function run()
    {
        $old = DB::connection('mysql_fin_migration')->table('investment_prices')->get();

        // creates a new progress bar based on item count
        $progressBar = $this->command->getOutput()->createProgressBar(count($old));

        // starts and displays the progress bar
        $progressBar->start();

        foreach ($old as $item) {
            InvestmentPrice::create([
                'date' => $item->date,
                'investment_id' => $item->investments_id,
                'price' => $item->price,
                'created_at' => $item->created_at,
                'updated_at' => $item->updated_at,
            ]);

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->command->getOutput()->writeln('');
    }
}
