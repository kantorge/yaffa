<?php

namespace Database\Seeders\Random;

use App\Models\AccountEntity;
use App\Models\Payee;
use App\Models\User;
use Illuminate\Database\Seeder;

class PayeeSeeder extends Seeder
{
    /**
     * Run the database seeds by creating random values
     */
    public function run(User $user, int $count = 5): void
    {
        AccountEntity::factory()
            ->count($count)
            ->for($user)
            ->for(Payee::factory()->withUser($user), 'config')
            ->create();
    }
}
