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
        $payee = AccountEntity::create(
            [
                'name' => 'Auchan',
                'active' => 1,
                'config_type' => 'payee',
            ]
        );

        $payeeConfig = Payee::create(
            [
                'categories_id' => Category::where('name', 'Alapanyag, fűszer, konzerv')->pluck('id')->first(),
            ]
        );
        $payee->config()->associate($payeeConfig);

        $payee->save();
    }
}
