<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\AccountGroup;
use App\Models\Currency;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AccountFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Account::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition(): array
    {
        return [
            'opening_balance' => $this->faker->numberBetween(0, 1000),
        ];
    }

    /**
     * Define a state, where the related assets are created for or used from a specific user.
     */
    public function withUser(User $user): self
    {
        return $this->state(function (array $attributes) use ($user) {
            // If the account group is not set, get one, or create a new one for the user
            if (! isset($attributes['account_group_id'])) {
                $attributes['account_group_id'] = $user->accountGroups()
                    ->inRandomOrder()
                    ->firstOr(fn () => AccountGroup::factory()->for($user)->create())
                    ->id;
            }

            // If the currency is not set, get one, or create a new one for the user
            if (! isset($attributes['currency_id'])) {
                $attributes['currency_id'] = $user->currencies()
                    ->inRandomOrder()
                    ->firstOr(fn () => Currency::factory()->for($user)->create())
                    ->id;
            }

            return $attributes;
        });
    }
}
