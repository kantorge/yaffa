<?php

use App\Account;
use App\AccountEntity;
use App\AccountGroup;
use App\Currency;
use Illuminate\Database\Seeder;

class AccountSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        /* specific values */
        $account = AccountEntity::create(
            [
                'name' => 'Pénztárca',
                'active' => 1,
                'config_type' => 'account',
            ]
        );

        $accountConfig = Account::create(
            [
                'opening_balance' => 1000,
                'account_groups_id' => AccountGroup::where('name', 'Készpénz')->pluck('id')->first(),
                'currencies_id' => Currency::where('iso_code', 'HUF')->pluck('id')->first(),
            ]
        );
        $account->config()->associate($accountConfig);

        $account->save();

        /* specific values */
        $account = AccountEntity::create(
            [
                'name' => 'Bankszámla',
                'active' => 1,
                'config_type' => 'account',
            ]
        );

        $accountConfig = Account::create(
            [
                'opening_balance' => 1000,
                'account_groups_id' => AccountGroup::where('name', 'Bankszámla')->pluck('id')->first(),
                'currencies_id' => Currency::where('iso_code', 'EUR')->pluck('id')->first(),
            ]
        );
        $account->config()->associate($accountConfig);

        $account->save();
    }
}
