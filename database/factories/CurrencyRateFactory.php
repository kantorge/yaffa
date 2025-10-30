<?php

namespace Database\Factories;

use App\Models\Currency;
use App\Models\CurrencyRate;
use Illuminate\Database\Eloquent\Factories\Factory;

class CurrencyRateFactory extends Factory
{
    protected $model = CurrencyRate::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'from_id' => Currency::factory(),
            'to_id' => Currency::factory(),
            'date' => $this->faker->date(),
            'rate' => $this->faker->randomFloat(4, 0.1, 10),
        ];
    }
}
