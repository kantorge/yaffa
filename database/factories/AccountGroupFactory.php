<?php

namespace Database\Factories;

use App\Models\AccountGroup;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AccountGroupFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = AccountGroup::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->text(mt_rand(10, 50)),
            'user_id' => User::factory(),
        ];
    }
}
