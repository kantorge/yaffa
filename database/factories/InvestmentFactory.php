<?php

namespace Database\Factories;

use App\Models\Currency;
use App\Models\Investment;
use App\Models\InvestmentGroup;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class InvestmentFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Investment::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'symbol' => $this->faker->boolean(80)
                ? Str::slug($this->faker->unique()->company()) : null,
            'isin' => $this->faker->boolean(80)
                ? $this->faker->asciify(str_repeat('*', 12)) : null,
            'auto_update' => false,
            'investment_price_provider' => null,
        ];
    }

    /**
     * Define a state, where the related assets are created for or used from a specific user.
     */
    public function withUser(User $user): self
    {
        return $this->state(function (array $attributes) use ($user) {
            // If the investment group is not set, get one, or create a new one for the user
            if (! isset($attributes['investment_group_id'])) {
                $attributes['investment_group_id'] = $user->investmentGroups()
                    ->inRandomOrder()
                    ->firstOr(fn () => InvestmentGroup::factory()->withUser($user)->create())
                    ->id;
            }

            // If the currency is not set, get one, or create a new one for the user
            if (! isset($attributes['currency_id'])) {
                $attributes['currency_id'] = $user->currencies()
                    ->inRandomOrder()
                    ->firstOr(fn () => Currency::factory()->withUser($user)->create())
                    ->id;
            }

            return $attributes;
        });
    }
}
