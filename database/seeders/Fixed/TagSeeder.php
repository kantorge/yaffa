<?php

namespace Database\Seeders\Fixed;

use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Seeder;

class TagSeeder extends Seeder
{
    /**
     * Run the database seeds by creating pre-defined values
     */
    public function run(User $user)
    {
        Tag::factory()->create([
            'name' => 'Kids',
            'user_id' => $user->id,
        ]);
        Tag::factory()->create([
            'name' => 'Holiday 2021',
            'user_id' => $user->id,
        ]);
    }
}
