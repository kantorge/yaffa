<?php

use App\Tag;
use Illuminate\Database\Seeder;

class TagTableSeeder extends Seeder
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
        factory(Tag::class, 5)->create();
    }

    private function seedFixed() {
        Tag::create([
            'name' => 'Gyerek'
        ]);
        Tag::create([
            'name' => 'Nyaralás'
        ]);
    }

    private function seedSql() {
        Eloquent::unguard();
        $path = 'storage/fin_migrations/tags.sql';
        DB::unprepared(file_get_contents($path));
    }

    private function seedDb()
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
