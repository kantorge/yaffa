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
        $this->callWith(\Database\Seeders\Fixed\UserSeeder::class, ['aliases' => ['demo']]);

        // Main user
        $alias = 'demo';
        $this->callWith(\Database\Seeders\Fixed\AccountGroupSeeder::class, ['alias' => $alias]);
        $this->callWith(\Database\Seeders\Fixed\CurrencySeeder::class, ['alias' => $alias]);
        $this->callWith(\Database\Seeders\Random\CurrencyRateSeeder::class, ['alias' => $alias]);
        $this->callWith(\Database\Seeders\Fixed\AccountSeeder::class, ['alias' => $alias]);
        $this->callWith(\Database\Seeders\Fixed\CategorySeeder::class, ['alias' => $alias]);
        $this->callWith(\Database\Seeders\Fixed\PayeeSeeder::class, ['alias' => $alias]);
        $this->callWith(\Database\Seeders\Fixed\InvestmentGroupSeeder::class, ['alias' => $alias]);
        $this->callWith(\Database\Seeders\Fixed\InvestmentSeeder::class, ['alias' => $alias]);
        // TODO: seed investment prices
        $this->callWith(\Database\Seeders\Fixed\TagSeeder::class, ['alias' => $alias]);
        $this->callWith(\Database\Seeders\Random\TransactionSeeder::class, ['alias' => $alias]);
    }
}
