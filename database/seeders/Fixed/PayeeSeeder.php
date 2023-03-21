<?php

namespace Database\Seeders\Fixed;

use App\Models\AccountEntity;
use App\Models\Category;
use App\Models\Payee;
use App\Models\User;
use Illuminate\Database\Seeder;

class PayeeSeeder extends Seeder
{
    /**
     * Run the database seeds by creating pre-defined values
     */
    public function run(User $user)
    {
        $payeeConfig = Payee::create(
            [
                'category_id' => Category::where('name', 'Groceries')->pluck('id')->first(),
            ]
        );
        AccountEntity::create(
            [
                'name' => 'Auchan',
                'active' => 1,
                'config_type' => 'payee',
                'config_id' => $payeeConfig->id,
                'user_id' => $user->id,
            ]
        );

        $payeeConfig = Payee::create();
        AccountEntity::create(
            [
                'name' => 'CBA',
                'active' => 1,
                'config_type' => 'payee',
                'config_id' => $payeeConfig->id,
                'user_id' => $user->id,
            ]
        );
    }
}
