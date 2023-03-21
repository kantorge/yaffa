<?php

namespace Database\Seeders\Fixed;

use App\Models\Account;
use App\Models\AccountEntity;
use App\Models\AccountGroup;
use App\Models\Currency;
use App\Models\User;
use Illuminate\Database\Seeder;

class AccountSeeder extends Seeder
{
    /**
     * Run the database seeds by creating pre-defined values
     */
    public function run(User $user)
    {
        $accountConfig = Account::create(
            [
                'opening_balance' => 1000,
                'account_group_id' => AccountGroup::where('name', 'Cash')->first()->id,
                'currency_id' => Currency::where('iso_code', 'EUR')->first()->id,
            ]
        );
        AccountEntity::create(
            [
                'name' => 'Wallet',
                'active' => 1,
                'config_type' => 'account',
                'config_id' => $accountConfig->id,
                'user_id' => $user->id,
            ]
        );

        $accountConfig = Account::create(
            [
                'opening_balance' => 1000,
                'account_group_id' => AccountGroup::where('name', 'Bank accounts')->first()->id,
                'currency_id' => Currency::where('iso_code', 'EUR')->first()->id,
            ]
        );
        AccountEntity::create(
            [
                'name' => 'Bank account',
                'active' => 1,
                'config_type' => 'account',
                'config_id' => $accountConfig->id,
                'user_id' => $user->id,
            ]
        );

        $accountConfig = Account::create(
            [
                'opening_balance' => 1000,
                'account_group_id' => AccountGroup::where('name', 'Investments')->first()->id,
                'currency_id' => Currency::where('iso_code', 'EUR')->first()->id,
            ]
        );
        AccountEntity::create(
            [
                'name' => 'Investment account EUR',
                'active' => 1,
                'config_type' => 'account',
                'config_id' => $accountConfig->id,
                'user_id' => $user->id,
            ]
        );

        $accountConfig = Account::create(
            [
                'opening_balance' => 1000,
                'account_group_id' => AccountGroup::where('name', 'Investments')->first()->id,
                'currency_id' => Currency::where('iso_code', 'USD')->first()->id,
            ]
        );
        AccountEntity::create(
            [
                'name' => 'Investment account USD',
                'active' => 1,
                'config_type' => 'account',
                'config_id' => $accountConfig->id,
                'user_id' => $user->id,
            ]
        );
    }
}
