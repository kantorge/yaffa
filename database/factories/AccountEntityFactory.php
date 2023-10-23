<?php

namespace Database\Factories;

use App\Models\AccountEntity;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;

/**
 * @template TModel of Model
 */
class AccountEntityFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Model|TModel>
     */
    protected $model = AccountEntity::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->word(),
            'active' => $this->faker->boolean(80),
            'alias' => $this->faker->boolean(30) ? $this->faker->word() : null,
            'user_id' => User::factory(),
        ];
    }
}
