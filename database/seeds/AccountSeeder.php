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
    public function run($extra)
    {
        switch($extra) {
            case 'random':
                $this->seedRandom();
                break;
            case 'fixed':
                $this->seedFixed();
                break;
            case 'sql':
                $this->seedSql();
                break;
            case 'db':
                $this->seedDb();
                break;
        }
    }

    private function seedRandom()
    {
        //TODO
    }

    private function seedFixed()
    {
        /* specific values */
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

        /* specific values */
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

    private function seedSql()
    {
        Eloquent::unguard();
        $path = 'storage/fin_migrations/accounts.sql';
        DB::unprepared(file_get_contents($path));
    }

    private function seedDb()
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
