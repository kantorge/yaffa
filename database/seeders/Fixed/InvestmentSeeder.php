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
     * @param User $user
     */
    public function run(User $user): void
    {
        Investment::factory()
            ->create(
                [
                    'name' => 'Magyar Telekom',
                    'active' => 1,
                    'symbol' => 'MTEL',
                    'investment_group_id' => $user->investmentGroups()->where('name', 'Stock')->first()->id,
                    'currency_id' => $user->currencies()->where('iso_code', 'HUF')->first()->id,
                    'user_id' => $user->id,
                ]
            );

        Investment::factory()
            ->create(
                [
                    'name' => 'Disney',
                    'active' => 1,
                    'symbol' => 'DIS',
                    'investment_group_id' => $user->investmentGroups()->where('name', 'Stock')->first()->id,
                    'currency_id' => $user->currencies()->where('iso_code', 'USD')->first()->id,
                    'investment_price_provider' => 'alpha_vantage',
                    'user_id' => $user->id,
                ]
            );

        Investment::factory()
            ->create(
                [
                    'name' => 'Euro investment',
                    'active' => 1,
                    'symbol' => 'E',
                    'investment_group_id' => $user->investmentGroups()->where('name', 'Mutual fund')->first()->id,
                    'currency_id' => $user->currencies()->where('iso_code', 'EUR')->first()->id,
                    'user_id' => $user->id,
                ]
            );

        Investment::factory()
            ->create(
                [
                    'name' => 'Test investment USD',
                    'active' => 1,
                    'symbol' => 'TIUSD',
                    'investment_group_id' => $user->investmentGroups()->where('name', 'Stock')->first()->id,
                    'currency_id' => $user->currencies()->where('iso_code', 'USD')->first()->id,
                    'investment_price_provider' => 'alpha_vantage',
                    'user_id' => $user->id,
                ]
            );

        Investment::factory()
            ->create(
                [
                    'name' => 'Test investment EUR',
                    'active' => 1,
                    'symbol' => 'TIEUR',
                    'investment_group_id' => $user->investmentGroups()->where('name', 'Stock')->first()->id,
                    'currency_id' => $user->currencies()->where('iso_code', 'EUR')->first()->id,
                    'investment_price_provider' => 'alpha_vantage',
                    'user_id' => $user->id,
                ]
            );
    }
}
