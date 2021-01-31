<?php

namespace Database\Seeders\Random;

use App\Models\Tag;
use Illuminate\Database\Seeder;

class TagSeeder extends Seeder
{
    /**
     * Run the database seeds by creating random values with factory
     *
     * @return void
     */
    public function run()
    {
        Tag::factory()->count(5)->create();
    }
}
