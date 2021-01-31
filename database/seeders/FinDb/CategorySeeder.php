<?php

namespace Database\Seeders\FinDb;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds by reading data from legacy system's remote DB
     *
     * @return void
     */
    public function run()
    {
        $old = DB::connection('mysql_fin_migration')->table('categories')->orderBy('id')->get();

        foreach ($old as $item) {
            Category::create([
                'id' => $item->id,
                'name' => $item->name,
                'parent_id' => $item->parent_id,
                'active' => $item->active,
            ]);

        }
    }
}
