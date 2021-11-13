<?php

namespace Database\Seeders;

use App\Models\User;
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

        $demoUser = User::where('email', 'demo@yaffa.cc')->first();

        $this->callWith(\Database\Seeders\Fixed\AccountGroupSeeder::class, ['user' => $demoUser]);
        $this->callWith(\Database\Seeders\Fixed\CurrencySeeder::class, ['user' => $demoUser]);
        $this->callWith(\Database\Seeders\Random\CurrencyRateSeeder::class, ['user' => $demoUser]);
        $this->callWith(\Database\Seeders\Fixed\AccountSeeder::class, ['user' => $demoUser]);
        $this->callWith(\Database\Seeders\Fixed\CategorySeeder::class, ['user' => $demoUser]);
        $this->callWith(\Database\Seeders\Fixed\PayeeSeeder::class, ['user' => $demoUser]);
        $this->callWith(\Database\Seeders\Fixed\InvestmentGroupSeeder::class, ['user' => $demoUser]);
        $this->callWith(\Database\Seeders\Fixed\InvestmentSeeder::class, ['user' => $demoUser]);
        // TODO: seed investment prices
        $this->callWith(\Database\Seeders\Fixed\TagSeeder::class, ['user' => $demoUser]);
        $this->callWith(\Database\Seeders\Random\TransactionSeeder::class, ['user' => $demoUser]);
    }
}
