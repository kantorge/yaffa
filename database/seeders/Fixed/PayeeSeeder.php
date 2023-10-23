<?php

namespace Database\Seeders\Fixed;

use App\Models\AccountEntity;
use App\Models\Category;
use App\Models\Payee;
use App\Models\User;
use Illuminate\Database\Seeder;

class PayeeSeeder extends Seeder
{
    /**
     * Run the database seeds by creating pre-defined values
     */
    public function run(User $user): void
    {
        AccountEntity::factory()
            ->for($user)
            ->for(
                Payee::factory()
                    ->withUser($user)
                    ->for(Category::where('name', 'Groceries')->first()),
                'config'
            )
            ->create(
                [
                    'name' => 'Auchan',
                    'active' => 1,
                ]
            );

        AccountEntity::factory()
            ->for($user)
            ->for(
                Payee::factory()
                    ->withUser($user),
                'config'
            )
            ->create(
                [
                    'name' => 'CBA',
                    'active' => 1,
                ]
            );
    }
}
