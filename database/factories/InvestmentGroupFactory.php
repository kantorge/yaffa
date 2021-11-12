<?php

namespace Database\Factories;

use App\Models\InvestmentGroup;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class InvestmentGroupFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = InvestmentGroup::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'name' => $this->faker->unique()->text(mt_rand(5, 25)),
            'user_id' => User::inRandomOrder()->first()->id,
        ];
    }
}
