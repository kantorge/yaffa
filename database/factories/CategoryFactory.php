<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CategoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->words(3, true),
            'active' => $this->faker->boolean(80),
            'parent_id' => null,
            'user_id' => User::inRandomOrder()->first()->id,
            'default_aggregation' => $this->faker->randomElement(['month', 'quarter', 'year']),
        ];
    }
}
