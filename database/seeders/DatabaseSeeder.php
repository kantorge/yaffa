<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database with general purpose sample data
     *
     * @return void
     */
    public function run()
    {
        $this->callWith(\Database\Seeders\Fixed\UserSeeder::class, ['aliases' => ['demo', 'other']]);

        // Main user
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

        // Other user
        $otherUser = User::where('email', 'other@yaffa.cc')->first();

        $this->callWith(\Database\Seeders\Random\AccountGroupSeeder::class, ['user' => $otherUser, 'count' => 3]);
        $this->callWith(\Database\Seeders\Random\CurrencySeeder::class, ['user' => $otherUser, 'count' => 2]);
        $this->callWith(\Database\Seeders\Random\AccountSeeder::class, ['user' => $otherUser, 'count' => 5]);
        $this->callWith(\Database\Seeders\Random\CategorySeeder::class, ['user' => $otherUser, 'count' => 5]);
        $this->callWith(\Database\Seeders\Random\PayeeSeeder::class, ['user' => $otherUser, 'count' => 5]);
        $this->callWith(\Database\Seeders\Random\InvestmentGroupSeeder::class, ['user' => $otherUser, 'count' => 3]);
        $this->callWith(\Database\Seeders\Random\InvestmentSeeder::class, ['user' => $otherUser, 'count' => 5]);
        // TODO: seed investment prices
        $this->callWith(\Database\Seeders\Random\TagSeeder::class, ['user' => $otherUser, 'count' => 5]);
        $this->callWith(\Database\Seeders\Random\TransactionSeeder::class, ['user' => $otherUser]);
    }
}
