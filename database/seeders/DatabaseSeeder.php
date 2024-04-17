<?php

namespace Database\Seeders;

use App\Models\User;
use Database\Seeders\Fixed\UserSeeder;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database with general purpose sample data
     */
    public function run(): void
    {
        $this->callWith(UserSeeder::class, ['aliases' => ['demo', 'other']]);

        // Main user
        $demoUser = User::where('email', 'demo@yaffa.cc')->first();

        $this->callWith(Fixed\AccountGroupSeeder::class, ['user' => $demoUser]);
        $this->callWith(Fixed\CurrencySeeder::class, ['user' => $demoUser]);
        $this->callWith(Random\CurrencyRateSeeder::class, ['user' => $demoUser]);
        $this->callWith(Fixed\AccountSeeder::class, ['user' => $demoUser]);
        $this->callWith(Fixed\CategorySeeder::class, ['user' => $demoUser]);
        $this->callWith(Fixed\PayeeSeeder::class, ['user' => $demoUser]);
        $this->callWith(Fixed\InvestmentGroupSeeder::class, ['user' => $demoUser]);
        $this->callWith(Fixed\InvestmentSeeder::class, ['user' => $demoUser]);
        // TODO: seed investment prices
        $this->callWith(Fixed\TagSeeder::class, ['user' => $demoUser]);
        $this->callWith(Random\TransactionSeeder::class, ['user' => $demoUser]);

        // Other user
        $otherUser = User::where('email', 'other@yaffa.cc')->first();

        $this->callWith(Random\AccountGroupSeeder::class, ['user' => $otherUser, 'count' => 3]);
        $this->callWith(Random\CurrencySeeder::class, ['user' => $otherUser, 'count' => 2]);
        $this->callWith(Random\AccountSeeder::class, ['user' => $otherUser, 'count' => 5]);
        $this->callWith(Random\CategorySeeder::class, ['user' => $otherUser, 'count' => 5]);
        $this->callWith(Random\PayeeSeeder::class, ['user' => $otherUser, 'count' => 5]);
        $this->callWith(Random\InvestmentGroupSeeder::class, ['user' => $otherUser, 'count' => 3]);
        $this->callWith(Random\InvestmentSeeder::class, ['user' => $otherUser, 'count' => 5]);
        // TODO: seed investment prices
        $this->callWith(Random\TagSeeder::class, ['user' => $otherUser, 'count' => 5]);
        $this->callWith(Random\TransactionSeeder::class, ['user' => $otherUser]);
    }
}
