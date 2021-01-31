<?php

namespace Database\Seeders\Fixed;

use App\Models\AccountEntity;
use App\Models\Category;
use App\Models\Payee;
use Illuminate\Database\Seeder;

class PayeeSeeder extends Seeder
{
    /**
     * Run the database seeds by creating pre-defined values
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
                'category_id' => Category::where('name', 'Alapanyag, fűszer, konzerv')->pluck('id')->first(),
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
