<?php

namespace Database\Seeders\Random;

use App\Models\AccountEntity;
use App\Models\Investment;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;

class InvestmentSeeder extends Seeder
{
    /**
     * Run the database seeds by creating pre-defined values
     */
    public function run(User $user, int $count = 5): void
    {
        if ($user) {
            $users = new Collection([$user]);
        } else {
            $users = User::all();
        }

        $users->each(function ($user) use ($count) {
            AccountEntity::factory()
                ->count($count)
                ->for($user)
                ->for(
                    Investment::factory()->withUser($user),
                    'config'
                )
                ->create();
        });
    }
}
