<?php

namespace Database\Seeders\FinDb;

use App\Models\Account;
use App\Models\AccountEntity;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AccountSeeder extends Seeder
{
    /**
     * Run the database seeds by reading data from legacy system's remote DB
     *
     * @return void
     */
    public function run()
    {
        $old = DB::connection('mysql_fin_migration')->table('accounts')->get();

        foreach ($old as $item) {
            $account = new AccountEntity(
                [
                    'name' => $item->name,
                    'active' => $item->active,
                    'config_type' => 'account',
                ]
            );

            $accountConfig = new Account(
                [
                    'opening_balance' => $item->opening_balance,
                    'account_group_id' => $item->account_groups_id,
                    'currency_id' => $item->currencies_id,
                ]
            );
            $accountConfig->save();

            $account->config()->associate($accountConfig);

            $account->save();
        }
    }
}
