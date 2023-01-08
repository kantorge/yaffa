<?php

namespace Database\Seeders\Fixed;

use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds by creating pre-defined values
     *
     * @return void
     */
    public function run(User $user)
    {
        $parentFood = Category::create([
            'name' => 'Food',
            'parent_id' => null,
            'user_id' => $user->id,
        ]);
        Category::create([
            'name' => 'Groceries',
            'parent_id' => $parentFood->id,
            'user_id' => $user->id,
        ]);
        Category::create([
            'name' => 'Restaurants, eating out',
            'parent_id' => $parentFood->id,
            'user_id' => $user->id,
        ]);

        $parentBills = Category::create([
            'name' => 'Bills',
            'parent_id' => null,
            'user_id' => $user->id,
        ]);
        Category::create([
            'name' => 'Electricity',
            'parent_id' => $parentBills->id,
            'user_id' => $user->id,
        ]);
        Category::create([
            'name' => 'Water',
            'parent_id' => $parentBills->id,
            'user_id' => $user->id,
        ]);

        $parentIncome = Category::create([
            'name' => 'Incomes',
            'parent_id' => null,
            'user_id' => $user->id,
        ]);
        Category::create([
            'name' => 'Net wage',
            'parent_id' => $parentIncome->id,
            'user_id' => $user->id,
        ]);
    }
}
