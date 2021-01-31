<?php

namespace Database\Seeders\Fixed;

use App\Models\Account;
use App\Models\AccountEntity;
use App\Models\AccountGroup;
use App\Models\Currency;
use Illuminate\Database\Seeder;

class AccountSeeder extends Seeder
{
    /**
     * Run the database seeds by creating pre-defined values
     *
     * @return void
     */
    public function run()
    {
        $account = new AccountEntity(
            [
                'name' => 'Pénztárca',
                'active' => 1,
                'config_type' => 'account',
            ]
        );

        $accountConfig = new Account(
            [
                'opening_balance' => 1000,
                'account_group_id' => AccountGroup::where('name', 'Készpénz')->pluck('id')->first(),
                'currency_id' => Currency::where('iso_code', 'HUF')->pluck('id')->first(),
            ]
        );
        $accountConfig->save();

        $account->config()->associate($accountConfig);

        $account->save();


        $account = new AccountEntity(
            [
                'name' => 'Bankszámla',
                'active' => 1,
                'config_type' => 'account',
            ]
        );

        $accountConfig = new Account(
            [
                'opening_balance' => 1000,
                'account_group_id' => AccountGroup::where('name', 'Bankszámla')->pluck('id')->first(),
                'currency_id' => Currency::where('iso_code', 'EUR')->pluck('id')->first(),
            ]
        );
        $accountConfig->save();

        $account->config()->associate($accountConfig);

        $account->save();
    }
}
