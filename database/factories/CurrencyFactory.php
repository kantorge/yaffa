<?php

namespace Database\Factories;

use App\Models\Currency;
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
        return [
            'name' => $this->faker->unique()->text(mt_rand(10, 50)),
            'iso_code' => $this->faker->unique()->currencyCode(),
            'num_digits' => $this->faker->numberBetween(0, 2),
            'suffix' => $this->faker->unique()->text(5),
        ];
    }
}
