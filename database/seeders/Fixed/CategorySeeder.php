<?php

namespace Database\Seeders\Fixed;

use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds by creating pre-defined values
     */
    public function run(User $user): void
    {
        $food = Category::create([
            'name' => 'Food',
            'parent_id' => null,
            'user_id' => $user->id,
        ]);
        Category::create([
            'name' => 'Groceries',
            'parent_id' => $food->id,
            'user_id' => $user->id,
        ]);
        Category::create([
            'name' => 'Restaurants, eating out',
            'parent_id' => $food->id,
            'user_id' => $user->id,
        ]);
    }
}
