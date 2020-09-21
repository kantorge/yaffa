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
    public function run()
    {
        /* random strings */
        //factory(Tag::class, 5)->create();

        /* specific values */
        Tag::create([
            'name' => 'Gyerek'
        ]);
        Tag::create([
            'name' => 'Nyaralás'
        ]);
    }
}
