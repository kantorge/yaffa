<?php

//use App\Investment;
use App\InvestmentPrice;
use Illuminate\Database\Seeder;

class InvestmentPriceSeeder extends Seeder
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
        //TODO
    }

    private function seedFixed() {
        //TODO
    }

    private function seedSql() {
        Eloquent::unguard();
        $path = 'storage/fin_migrations/investment_prices.sql';
        DB::unprepared(file_get_contents($path));
    }

    private function seedDb()
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
