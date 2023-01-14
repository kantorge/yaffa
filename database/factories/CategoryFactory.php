<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CategoryFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Category::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->text(mt_rand(10, 25)),
            'active' => $this->faker->boolean(80),
            'parent_id' => null,
            'user_id' => User::inRandomOrder()->first()->id,
        ];
    }
}
