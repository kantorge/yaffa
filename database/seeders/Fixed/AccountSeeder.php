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
     *
     * @return void
     */
    public function run(User $user)
    {
        $accountConfig = Account::create(
            [
                'opening_balance' => 1000,
                'account_group_id' => AccountGroup::where('name', 'Cash')->pluck('id')->first(),
                'currency_id' => Currency::where('iso_code', 'EUR')->pluck('id')->first(),
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
                'account_group_id' => AccountGroup::where('name', 'Bank accounts')->pluck('id')->first(),
                'currency_id' => Currency::where('iso_code', 'EUR')->pluck('id')->first(),
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
    }
}
