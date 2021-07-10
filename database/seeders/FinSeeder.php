<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class FinSeeder extends Seeder
{
    /**
     * Seed the application's database from legacy version
     *
     * @return void
     */
    public function run()
    {
        $this->call(\Database\Seeders\FinDb\TransactionTypeSeeder::class);
        $this->call(\Database\Seeders\FinDb\AccountGroupSeeder::class);
        $this->call(\Database\Seeders\FinDb\CurrencySeeder::class);
        $this->call(\Database\Seeders\FinDb\CurrencyRateSeeder::class);
        $this->call(\Database\Seeders\FinDb\AccountSeeder::class);
        $this->call(\Database\Seeders\FinDb\CategorySeeder::class);
        $this->call(\Database\Seeders\FinDb\PayeeSeeder::class);
        $this->call(\Database\Seeders\FinDb\InvestmentGroupSeeder::class);
        $this->call(\Database\Seeders\FinDb\InvestmentSeeder::class);
        $this->call(\Database\Seeders\FinDb\InvestmentPriceSeeder::class);
        $this->call(\Database\Seeders\FinDb\TagSeeder::class);
        $this->call(\Database\Seeders\FinDb\TransactionSeeder::class);
    }
}
