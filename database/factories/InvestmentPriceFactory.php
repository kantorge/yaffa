<?php

namespace Database\Factories;

use App\Models\Investment;
use Illuminate\Database\Eloquent\Factories\Factory;

class InvestmentPriceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition(): array
    {
        $investment = Investment::inRandomOrder()->first();

        $baseAttributes = [
            'date' => $this->faker->date('Y-m-d'),
            'price' => $this->faker->randomFloat(2, 1, 10000),
        ];

        // If an investment exists, use it
        if ($investment) {
            $baseAttributes['investment_id'] = $investment->id;
        }

        return $baseAttributes;
    }
}
