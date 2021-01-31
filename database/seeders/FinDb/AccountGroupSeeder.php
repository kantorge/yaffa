<?php

namespace Database\Seeders\FinDb;

use App\Models\AccountGroup;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AccountGroupSeeder extends Seeder
{
    /**
     * Run the database seeds by reading data from legacy system's remote DB
     *
     * @return void
     */
    public function run()
    {
        $old = DB::connection('mysql_fin_migration')->table('account_groups')->get();

        foreach ($old as $item) {
            AccountGroup::create([
                'id' => $item->id,
                'name' => $item->name,
            ]);
        }
    }
}
