<?php

namespace Database\Seeders\Random;

use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds by creating pre-defined values
     *
     * @return void
     */
    public function run(User $user, int $count = 5)
    {
        if ($user) {
            $users = new Collection([$user]);
        } else {
            $users = User::all();
        }

        $users->each(function ($user) use ($count) {
            // Create some parent items
            $parents = Category::factory()
                ->count($count)
                ->for($user)
                ->create();

            // Create child items
            $parents->each(function ($parent) use ($count, $user) {
                Category::factory()
                    ->count($count)
                    ->for($user)
                    ->create([
                        'parent_id' => $parent->id,
                    ]);
            });
        });
    }
}
