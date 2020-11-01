<?php

use App\AccountEntity;
use App\Category;
use App\Payee;
use Illuminate\Database\Seeder;

class PayeeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        /* specific values */
        $payee = new AccountEntity(
            [
                'name' => 'Auchan',
                'active' => 1,
                'config_type' => 'payee',
            ]
        );

        $payeeConfig = new Payee(
            [
                'categories_id' => Category::where('name', 'Alapanyag, fűszer, konzerv')->pluck('id')->first(),
            ]
        );
        $payeeConfig->save();

        $payee->config()->associate($payeeConfig);

        $payee->save();

        /* specific values */
        $payee = new AccountEntity(
            [
                'name' => 'CBA',
                'active' => 1,
                'config_type' => 'payee',
            ]
        );

        $payeeConfig = new Payee();
        $payeeConfig->save();

        $payee->config()->associate($payeeConfig);

        $payee->save();
    }
}
