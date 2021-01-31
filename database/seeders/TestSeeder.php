<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class TestSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        $this->call(\Database\Seeders\Fixed\TransactionTypeSeeder::class);
        $this->call(\Database\Seeders\Fixed\AccountGroupSeeder::class);  //random exists, but random account creation is TODO
        $this->call(\Database\Seeders\Fixed\CurrencySeeder::class);  //random exists, but random account creation is TODO
        $this->call(\Database\Seeders\Random\CurrencyRateSeeder::class);  //TODO: fixed is missing, but might not be necessary to add
        $this->call(\Database\Seeders\Fixed\AccountSeeder::class); //TODO: random seeder AND factory
        $this->call(\Database\Seeders\Fixed\CategorySeeder::class); //TODO: random seeder AND factory
        $this->call(\Database\Seeders\Fixed\PayeeSeeder::class); //TODO: random seeder AND factory
        $this->call(\Database\Seeders\Fixed\InvestmentGroupSeeder::class); //TODO: random exists, but cannot be used without random investments
        $this->call(\Database\Seeders\Fixed\InvestmentSeeder::class); //TODO: random seeder AND factory
        //$this->call(InvestmentPriceSeeder::class, 'db'); TODO: create fixed values
        $this->call(\Database\Seeders\Random\TagSeeder::class);
        $this->call(\Database\Seeders\Random\TransactionSeeder::class);
    }
}
