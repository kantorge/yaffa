<?php

namespace Database\Seeders\Fixed;

use App\Models\Tag;
use Illuminate\Database\Seeder;

class TagSeeder extends Seeder
{
    /**
     * Run the database seeds by creating pre-defined values
     *
     * @return void
     */
    public function run()
    {
        Tag::create([
            'name' => 'Gyerek'
        ]);
        Tag::create([
            'name' => 'Nyaralás'
        ]);
    }
}
