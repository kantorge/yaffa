<?php

namespace Database\Seeders\Fixed;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds by creating pre-defined values
     *
     * @return void
     */
    public function run()
    {
        Category::create([
            'name' => 'Élelmiszer',
            'parent_id' => null,
        ]);
        Category::create([
            'name' => 'Alapanyag, fűszer, konzerv',
            'parent_id' => Category::where('name', 'Élelmiszer')->pluck('id')->first(),
        ]);
        Category::create([
            'name' => 'Étterem',
            'parent_id' => Category::where('name', 'Élelmiszer')->pluck('id')->first(),
        ]);
    }
}
