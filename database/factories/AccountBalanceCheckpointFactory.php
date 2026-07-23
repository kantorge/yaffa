<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\AccountEntity;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\AccountBalanceCheckpoint>
 */
class AccountBalanceCheckpointFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'account_entity_id' => function (array $attributes): int {
                /** @var User $user */
                $user = User::find($attributes['user_id']) ?? User::factory()->create();

                return AccountEntity::factory()
                    ->for($user)
                    ->for(Account::factory()->withUser($user), 'config')
                    ->create()
                    ->id;
            },
            'checkpoint_date' => $this->faker->date('Y-m-d'),
            'checkpoint_type' => $this->faker->randomElement(['cash', 'investment', 'total']),
            'balance' => $this->faker->randomFloat(2, 0, 10000),
            'note' => $this->faker->boolean(35) ? $this->faker->sentence() : null,
            'active' => true,
            'source' => 'manual',
            'source_document_id' => null,
        ];
    }

    public function forAccount(AccountEntity $accountEntity): self
    {
        return $this->state(fn (array $attributes): array => [
            'user_id' => $accountEntity->user_id,
            'account_entity_id' => $accountEntity->id,
        ]);
    }
}
