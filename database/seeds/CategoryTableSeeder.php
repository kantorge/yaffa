<?php

use App\Category;
use Illuminate\Database\Seeder;

class CategoryTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        /* specific values */
        Category::create([
            'name' => 'Élelmiszer',
            'parent_id' => null
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
