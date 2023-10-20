<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\AccountEntity;
use App\Models\Investment;
use App\Models\Payee;
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
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->word(),
            'active' => $this->faker->boolean(80),
            'alias' => $this->faker->boolean(30) ? $this->faker->word() : null,
            'user_id' => null,
        ];
    }

    public function payee(array $configAttributes = []): AccountEntityFactory
    {
        return $this->state(function (array $attributes) use ($configAttributes) {
            /** @var User $user */
            $user = User::findOr(
                $attributes['user_id'] ?? null,
                [],
                fn () => User::factory()->create()
            );

            // Merge and use the passed attributes as AccountEntity attributes
            // If not passed, leave it empty to use factory defaults
            return array_merge(
                $attributes,
                [
                    'user_id' => $user->id,
                    'config_type' => 'payee',
                    'config_id' => Payee::factory()->withUser($user)->create($configAttributes)->id,
                ]
            );
        });
    }

    /**
     * @param  array  $configAttributes Optionally pass the properties to be used.
     */
    public function account(array $configAttributes = []): AccountEntityFactory
    {
        return $this->state(function (array $attributes) use ($configAttributes) {
            /** @var User $user */
            $user = User::findOr(
                $attributes['user_id'] ?? null,
                [],
                fn () => User::factory()->create()
            );

            // Merge and use the passed attributes as AccountEntity attributes
            // If not passed, leave it empty to use factory defaults
            return array_merge(
                $attributes,
                [
                    'user_id' => $user->id,
                    'config_type' => 'account',
                    'config_id' => Account::factory()->withUser($user)->create($configAttributes)->id,
                ]
            );
        });
    }

    /**
     * @param  array  $configAttributes Optionally pass the properties to be used.
     */
    public function investment(array $configAttributes = []): AccountEntityFactory
    {
        return $this->state(function (array $attributes) use ($configAttributes) {
            /** @var User $user */
            $user = User::findOr(
                $attributes['user_id'] ?? null,
                [],
                fn () => User::factory()->create()
            );

            // Merge and use the passed attributes as AccountEntity attributes
            // If not passed, leave it empty to use factory defaults
            return array_merge(
                $attributes,
                [
                    'user_id' => $user->id,
                    'config_type' => 'investment',
                    'config_id' => Investment::factory()->withUser($user)->create($configAttributes)->id,
                ]
            );
        });
    }
}
