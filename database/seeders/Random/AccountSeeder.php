<?php

namespace Database\Seeders\Random;

use App\Models\Account;
use App\Models\AccountEntity;
use App\Models\User;
use Illuminate\Database\Seeder;

class AccountSeeder extends Seeder
{
    /**
     * Run the database seeds by creating random values with factory for the specified user
     */
    public function run(User $user, int $count = 5): void
    {
        AccountEntity::factory()
            ->count($count)
            ->for($user)
            ->for(
                Account::factory()->withUser($user),
                'config'
            )
            ->create();
    }
}
