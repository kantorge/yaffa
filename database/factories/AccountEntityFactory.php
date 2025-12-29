<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;

/**
 * @template TModel of Model
 */
class AccountEntityFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition(): array
    {
        return [
            // Ensure the name is unique and at least 3 characters long
            'name' => function () {
                do {
                    $word = $this->faker->unique()->word();
                } while (mb_strlen($word) < 3);
                return $word;
            },
            'active' => $this->faker->boolean(80),
            'alias' => $this->faker->boolean(30) ? $this->faker->word() : null,
            'user_id' => User::factory(),
        ];
    }
}
