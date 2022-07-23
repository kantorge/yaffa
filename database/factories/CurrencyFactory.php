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
     *
     * @return array
     */
    public function definition()
    {
        $currency = $this->faker->currencyArray();

        return [
            'name' => $currency['name'],
            'iso_code' => $currency['isoCode'],
            'num_digits' => $currency['minorUnits'],
            'suffix' => $currency['symbol'],
            'base' => null,
            'auto_update' => $this->faker->boolean,
            'user_id' => User::inRandomOrder()->first()->id,
        ];
    }
}
