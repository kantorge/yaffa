<?php

namespace Database\Factories;

use App\Models\AccountEntity;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AccountBalanceCheckpoint>
 */
class AccountBalanceCheckpointFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'account_entity_id' => AccountEntity::factory(),
            'checkpoint_date' => fake()->dateTimeBetween('-1 year', 'now'),
            'balance' => fake()->randomFloat(2, 100, 50000),
            'note' => fake()->optional()->sentence(),
            'active' => true,
        ];
    }

    /**
     * Indicate that the checkpoint is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'active' => false,
        ]);
    }
}
