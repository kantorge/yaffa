<?php

namespace Database\Seeders\Random;

//use App\Models\Tag;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Seeder;

class TransactionSeeder extends Seeder
{
    /**
     * Run the database seeds by creating random values with factory
     */
    public function run(User $user): void
    {
        // Create standard withdrawals
        Transaction::factory()
            ->count(rand(10, 20))
            ->for($user)
            ->withdrawal($user)
            ->create();

        // Create deposits
        Transaction::factory()
            ->count(rand(10, 20))
            ->for($user)
            ->deposit($user)
            ->create();

        // Create transfers
        Transaction::factory()
            ->count(rand(5, 10))
            ->for($user)
            ->transfer($user)
            ->create();

        // Create standard withdrawals with schedule
        Transaction::factory()
            ->count(rand(5, 10))
            ->for($user)
            ->withdrawal_schedule($user)
            ->create();

        // Investments - buy
        Transaction::factory()
            ->count(rand(5, 10))
            ->for($user)
            ->buy($user)
            ->create();
    }
}
