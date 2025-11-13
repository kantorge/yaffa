<?php

namespace Database\Factories;

use App\Models\Currency;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CurrencyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition(): array
    {
        $currency = $this->faker->currencyArray();

        return [
            'name' => $currency['name'],
            'iso_code' => $currency['iso_code'],
            'base' => null,
            'auto_update' => $this->faker->boolean,
            'user_id' => User::factory(),
        ];
    }

    /**
     * Create a state where the user can provide an array of ISO codes to select from,
     * assuming the selected currencies are also available in the currency faker provider.
     */
    public function fromIsoCodes(array $isoCodes): CurrencyFactory
    {
        return $this->state(function (array $attributes) use ($isoCodes) {
            $isoCode = $this->faker->randomElement($isoCodes);
            $currency = $this->faker->currencyArrayByIsoCode($isoCode);

            return [
                'name' => $currency['name'],
                'iso_code' => $currency['iso_code'],
                'base' => null,
                'auto_update' => $this->faker->boolean,
                'user_id' => $attributes['user_id'] ?? User::factory()->create()->id,
            ];
        });
    }
}
