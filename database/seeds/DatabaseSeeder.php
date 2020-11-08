<?php

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        $this->call(AccountGroupTableSeeder::class);
        $this->call(CurrencyTableSeeder::class);
        $this->call(CurrencyRateSeeder::class);
        $this->call(AccountSeeder::class);
        $this->call(CategoryTableSeeder::class);
        $this->call(PayeeSeeder::class);
        $this->call(InvestmentGroupTableSeeder::class);
        $this->call(InvestmentTableSeeder::class);
        $this->call(TagTableSeeder::class);
        $this->call(TransactionSeeder::class);
    }
}
