<?php

namespace Database\Factories;

use App\Models\Currency;
use App\Models\CurrencyRate;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use InvalidArgumentException;

class CurrencyRateFactory extends Factory
{
    protected $model = CurrencyRate::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        // Create a user first to ensure both currencies belong to the same user
        $user = User::factory()->create();

        return [
            'from_id' => Currency::factory()->for($user),
            'to_id' => Currency::factory()->for($user),
            'date' => $this->faker->date(),
            'rate' => $this->faker->randomFloat(4, 0.1, 10),
        ];
    }

    /**
     * State for creating currency rates for a specific user's currencies.
     */
    public function forUser(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'from_id' => Currency::factory()->for($user),
            'to_id' => Currency::factory()->for($user),
        ]);
    }

    /**
     * State for creating currency rates between specific currencies.
     */
    public function betweenCurrencies(Currency $fromCurrency, Currency $toCurrency): static
    {
        return $this->state(function (array $attributes) use ($fromCurrency, $toCurrency) {
            // Ensure both currencies belong to the same user
            if ($fromCurrency->user_id !== $toCurrency->user_id) {
                throw new InvalidArgumentException('Both currencies must belong to the same user');
            }

            return [
                'from_id' => $fromCurrency->id,
                'to_id' => $toCurrency->id,
            ];
        });
    }
}
