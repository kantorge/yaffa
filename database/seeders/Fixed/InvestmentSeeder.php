<?php

namespace Database\Seeders\Fixed;

use App\Models\AccountEntity;
use App\Models\Investment;
use App\Models\User;
use Illuminate\Database\Seeder;

class InvestmentSeeder extends Seeder
{
    /**
     * Run the database seeds by creating pre-defined values
     */
    public function run(User $user): void
    {
        $investmentConfig = Investment::create([
            'symbol' => 'MTEL',
            'investment_group_id' => $user->investmentGroups()->where('name', 'Stock')->first()->id,
            'currency_id' => $user->currencies()->where('iso_code', 'HUF')->first()->id,
        ]);
        AccountEntity::create([
            'name' => 'Magyar Telekom',
            'active' => 1,
            'config_type' => 'investment',
            'config_id' => $investmentConfig->id,
            'user_id' => $user->id,
        ]);

        $investmentConfig = Investment::create([
            'symbol' => 'DIS',
            'investment_group_id' => $user->investmentGroups()->where('name', 'Stock')->first()->id,
            'currency_id' => $user->currencies()->where('iso_code', 'USD')->first()->id,
            'investment_price_provider' => 'alpha_vantage',
        ]);
        AccountEntity::create([
            'name' => 'Disney',
            'active' => 1,
            'config_type' => 'investment',
            'config_id' => $investmentConfig->id,
            'user_id' => $user->id,
        ]);

        $investmentConfig = Investment::create([
            'symbol' => 'E',
            'investment_group_id' => $user->investmentGroups()->where('name', 'Mutual fund')->first()->id,
            'currency_id' => $user->currencies()->where('iso_code', 'EUR')->first()->id,
        ]);
        AccountEntity::create([
            'name' => 'Euro investment',
            'active' => 1,
            'config_type' => 'investment',
            'config_id' => $investmentConfig->id,
            'user_id' => $user->id,
        ]);

        $investmentConfig = Investment::create([
            'symbol' => 'TIUSD',
            'investment_group_id' => $user->investmentGroups()->where('name', 'Stock')->first()->id,
            'currency_id' => $user->currencies()->where('iso_code', 'USD')->first()->id,
            'investment_price_provider' => 'alpha_vantage',
        ]);
        AccountEntity::create([
            'name' => 'Test investment USD',
            'active' => 1,
            'config_type' => 'investment',
            'config_id' => $investmentConfig->id,
            'user_id' => $user->id,
        ]);

        $investmentConfig = Investment::create([
            'symbol' => 'TIEUR',
            'investment_group_id' => $user->investmentGroups()->where('name', 'Stock')->first()->id,
            'currency_id' => $user->currencies()->where('iso_code', 'EUR')->first()->id,
            'investment_price_provider' => 'alpha_vantage',
        ]);
        AccountEntity::create([
            'name' => 'Test investment EUR',
            'active' => 1,
            'config_type' => 'investment',
            'config_id' => $investmentConfig->id,
            'user_id' => $user->id,
        ]);
    }
}
