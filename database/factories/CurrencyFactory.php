<?php

namespace Database\Factories;

use App\Models\Currency;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CurrencyFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Currency::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        $currency = $this->faker->currencyArray();

        // Get a user that doesn't have this currency
        $user = User::doesntHave(
            'currencies',
            'and',
            fn ($query) => $query->where('iso_code', $currency['iso_code'])
        )
            ->inRandomOrder()
            ->firstOr(fn () => User::factory()->create());

        return [
            'name' => $currency['name'],
            'iso_code' => $currency['iso_code'],
            'num_digits' => $currency['num_digits'],
            'base' => null,
            'auto_update' => $this->faker->boolean,
            'user_id' => $user->id,
        ];
    }

    public function withUser(User $user): self
    {
        return $this->state(function (array $attributes) use ($user) {
            return [
                'user_id' => $user->id,
            ];
        });
    }
}
