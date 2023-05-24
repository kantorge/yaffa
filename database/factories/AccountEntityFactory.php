<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\AccountEntity;
use App\Models\Payee;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;

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
            'user_id' => User::inRandomOrder()->first()->id,
        ];
    }

    public function payee(User $user = null): AccountEntityFactory
    {
        return $this->state(function (array $attributes) use ($user) {
            if (!$user) {
                $user = User::find($attributes['user_id']);
            }

            return [
                'name' => $this->faker->company(),
                'config_type' => 'payee',
                'config_id' => Payee::factory()->create([
                    'category_id' => $this->faker->boolean(50) ? $user->categories()->inRandomOrder()->first()->id : null,
                ])->id,
            ];
        });
    }

    /**
     * @param User|null $user Optionally pass the owner of the created asset. Selected by random from existing users if null.
     * @param array $configAttributes Optionally pass the properties to be used.
     * @return AccountEntityFactory
     */
    public function account(User $user = null, array $configAttributes = []): AccountEntityFactory
    {
        return $this->state(function (array $attributes) use ($user, $configAttributes) {
            if (!$user) {
                $user = User::find($attributes['user_id']);
            }

            $configAttributes = array_merge(
                [
                    'account_group_id' => $user->accountGroups()->inRandomOrder()->first()->id,
                    'currency_id' => $user->currencies()->inRandomOrder()->first()->id,
                ],
                $configAttributes
            );

            return [
                'config_type' => 'account',
                'config_id' => Account::factory()->create($configAttributes)->id,
            ];
        });
    }
}
