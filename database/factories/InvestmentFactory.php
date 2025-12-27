<?php

namespace Database\Factories;

use App\Models\Currency;
use App\Models\InvestmentGroup;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class InvestmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition(): array
    {
        $user = User::has('investmentGroups')->has('currencies')->inRandomOrder()->first();

        $name = $this->faker->unique()->company();

        return [
            'name' => $name,
            'symbol' => Str::slug($name),
            'isin' => $this->faker->asciify(str_repeat('*', 12)),
            'comment' => $this->faker->boolean(25) ? $this->faker->text(191) : null,
            'active' => $this->faker->boolean(80),
            'auto_update' => false,
            'investment_group_id' => $user->investmentGroups()->inRandomOrder()->first()->id,
            'currency_id' => $user->currencies()->inRandomOrder()->first()->id,
            'user_id' => $user->id,
        ];
    }

    /**
     * Define a state, where the related assets are created for or used from a specific user.
     */
    public function withUser(User $user): self
    {
        return $this->state(function (array $attributes) use ($user) {
            // If the investment group is not set, get one, or create a new one for the user
            if (!isset($attributes['investment_group_id'])) {
                $attributes['investment_group_id'] = $user->investmentGroups()
                    ->inRandomOrder()
                    ->firstOr(fn() => InvestmentGroup::factory()->for($user)->create())
                    ->id;
            }

            // If the currency is not set, get one, or create a new one for the user
            if (!isset($attributes['currency_id'])) {
                $attributes['currency_id'] = $user->currencies()
                    ->inRandomOrder()
                    ->firstOr(fn() => Currency::factory()->for($user)->create())
                    ->id;
            }

            return $attributes;
        });
    }
}
