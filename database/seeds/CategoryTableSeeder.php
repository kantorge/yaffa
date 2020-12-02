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

    private function seedRandom() {
        //TODO
    }

    private function seedFixed() {
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

    private function seedSql() {
        Eloquent::unguard();
        $path = 'storage/fin_migrations/categories.sql';
        DB::unprepared(file_get_contents($path));
    }

    private function seedDb()
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
