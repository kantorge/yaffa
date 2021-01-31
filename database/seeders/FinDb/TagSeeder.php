<?php

namespace Database\Seeders\FinDb;

use App\Models\Tag;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TagSeeder extends Seeder
{
    /**
     * Run the database seeds by reading data from legacy system's remote DB
     *
     * @return void
     */
    public function run()
    {
        $old = DB::connection('mysql_fin_migration')->table('tags')->get();

        foreach ($old as $item) {
            Tag::create([
                'id' => $item->id,
                'name' => $item->name,
            ]);

       }
    }
}
