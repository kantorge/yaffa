<?php

namespace Database\Factories;

use App\Models\Account;
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
        $user = User::has('accountGroups')->has('currencies')->inRandomOrder()->first();

        return [
            'opening_balance' => $this->faker->numberBetween(-1000, 1000), //TODO: make range context aware, e.g. based on currency
            'account_group_id' => $user->accountGroups()->inRandomOrder()->first()->id,
            'currency_id' => $user->currencies()->inRandomOrder()->first()->id,
        ];
    }
}
