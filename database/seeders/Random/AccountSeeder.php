<?php

namespace Database\Seeders\Random;

use App\Models\AccountEntity;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;

class AccountSeeder extends Seeder
{
    /**
     * Run the database seeds by creating random values
     */
    public function run(User $user, int $count = 5)
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
                ->account($user)
                ->create();
        });
    }
}
