<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\AccountEntity;
use App\Models\Payee;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AccountEntityFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = AccountEntity::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'name' => $this->faker->unique()->word(),
            'active' => $this->faker->boolean(80) ? true : false,
            'user_id' => User::inRandomOrder()->first()->id,
        ];
    }

    public function payee(User $user = null)
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

    public function account(User $user = null)
    {
        return $this->state(function (array $attributes) use ($user) {
            if (!$user) {
                $user = User::find($attributes['user_id']);
            }

            return [
                'config_type' => 'account',
                'config_id' => Account::factory()->create([
                    'account_group_id' => $user->accountGroups()->inRandomOrder()->first()->id,
                    'currency_id' => $user->currencies()->inRandomOrder()->first()->id,
                ])->id,
            ];
        });
    }
}
