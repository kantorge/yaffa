<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CategoryLearning>
 */
class CategoryLearningFactory extends Factory
{
    public function definition(): array
    {
        $user = User::factory()->create();

        return [
            'user_id' => $user->id,
            'item_description' => fake()->word(),
            'category_id' => Category::factory()->create(['user_id' => $user->id])->id,
            'usage_count' => $this->faker->numberBetween(0, 100),
        ];
    }
}
