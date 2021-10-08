<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DemoSeeder extends Seeder
{
    /**
     * Seed the application's database with data for demonstrative purpose
     *
     * @return void
     */
    public function run()
    {
        $this->call(\Database\Seeders\Fixed\AccountGroupSeeder::class);
        $this->call(\Database\Seeders\Fixed\CurrencySeeder::class);
        $this->call(\Database\Seeders\Random\CurrencyRateSeeder::class);
        $this->call(\Database\Seeders\Fixed\AccountSeeder::class);
        $this->call(\Database\Seeders\Fixed\CategorySeeder::class);
        $this->call(\Database\Seeders\Fixed\PayeeSeeder::class);
        $this->call(\Database\Seeders\Fixed\InvestmentGroupSeeder::class);
        $this->call(\Database\Seeders\Fixed\InvestmentSeeder::class);
        //$this->call(InvestmentPriceSeeder::class, 'db'); TODO: create fixed values
        $this->call(\Database\Seeders\Fixed\TagSeeder::class);
        $this->call(\Database\Seeders\Random\TransactionSeeder::class);
    }
}
