<?php

namespace Database\Seeders\Fixed;

use App\Models\AccountGroup;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Database\Seeder;

class AccountGroupSeeder extends Seeder
{
    /**
     * Run the database seeds by creating pre-defined values
     */
    public function run(User $user)
    {
        AccountGroup::factory()
            ->for($user)
            ->count(4)
            ->state(new Sequence(
                ['name' => 'Cash'],
                ['name' => 'Bank accounts'],
                ['name' => 'Credits and loans'],
                ['name' => 'Investments'],
            ))
            ->create();
    }
}
