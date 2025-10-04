<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TagFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->text(mt_rand(5, 15)),
            'active' => true,
            'user_id' => User::inRandomOrder()->first()->getAttribute('id'),
        ];
    }
}
