<?php

namespace Database\Seeders\Random;

//use App\Models\Tag;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Seeder;

class TransactionSeeder extends Seeder
{
    /**
     * Run the database seeds by creating random values with factory for the specified user
     */
    public function run(User $user): void
    {
        // Create standard withdrawals
        Transaction::factory()
            ->for($user)
            ->count(rand(10, 20))
            ->withdrawal($user)
            ->create();

        // Create deposits
        Transaction::factory()
            ->for($user)
            ->count(rand(10, 20))
            ->deposit($user)
            ->create();

        // Create transfers
        Transaction::factory()
            ->for($user)
            ->count(rand(5, 10))
            ->transfer($user)
            ->create();

        // Create standard withdrawals with schedule
        Transaction::factory()
            ->for($user)
            ->count(rand(5, 10))
            ->withdrawal_schedule($user)
            ->create();

        // Investments - buy
        Transaction::factory()
            ->for($user)
            ->count(rand(5, 10))
            ->buy($user)
            ->create();
    }
}
