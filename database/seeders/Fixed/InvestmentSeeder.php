<?php

namespace Database\Seeders\Fixed;

use App\Models\Investment;
use App\Models\User;
use Illuminate\Database\Seeder;

class InvestmentSeeder extends Seeder
{
    /**
     * Run the database seeds by creating pre-defined values
     *
     * @return void
     */
    public function run(User $user)
    {
        Investment::factory()
        ->create([
                'name' => 'Magyar Telekom',
                'active' => 1,
                'symbol' => 'MTEL',
                'investment_group_id' => $user->investmentGroups()->where('name', 'Stock')->first()->id,
                'currency_id' => $user->currencies()->where('iso_code', 'HUF')->first()->id,
                'user_id' => $user->id,
            ]
        );

        Investment::factory()
        ->create([
                'name' => 'Disney',
                'active' => 1,
                'symbol' => 'DIS',
                'investment_group_id' => $user->investmentGroups()->where('name', 'Stock')->pluck('id')->first(),
                'currency_id' => $user->currencies()->where('iso_code', 'USD')->pluck('id')->first(),
                'investment_price_provider_id' => 1,
                'user_id' => $user->id,
            ]
        );

        Investment::factory()
        ->create([
                'name' => 'Euro investment',
                'active' => 1,
                'symbol' => 'E',
                'investment_group_id' => $user->investmentGroups()->where('name', 'Mutual fund')->pluck('id')->first(),
                'currency_id' => $user->currencies()->where('iso_code', 'EUR')->pluck('id')->first(),
                'user_id' => $user->id,
            ]
        );
    }
}
