<?php

namespace Database\Seeders\Random;

use App\Models\Investment;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;

class InvestmentSeeder extends Seeder
{
    /**
     * Run the database seeds by creating pre-defined values
     */
    public function run(User $user, int $count = 5)
    {
        if ($user) {
            $users = new Collection([$user]);
        } else {
            $users = User::all();
        }

        $users->each(function ($user) use ($count) {
            Investment::factory()
                ->count($count)
                ->for($user)
                ->create();
        });
    }
}
