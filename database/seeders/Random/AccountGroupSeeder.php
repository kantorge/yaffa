<?php

namespace Database\Seeders\Random;

use App\Models\AccountGroup;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;

class AccountGroupSeeder extends Seeder
{
    /**
     * Run the database seeds by creating random values with factory
     */
    public function run(?User $user, $count = 5): void
    {
        if ($user) {
            $users = new Collection([$user]);
        } else {
            $users = User::all();
        }

        $users->each(function ($user) use ($count) {
            AccountGroup::factory()
                ->count($count)
                ->for($user)
                ->create();
        });
    }
}
