<?php

namespace Database\Factories;

use App\Models\Payee;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PayeeFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Payee::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        $user = User::has('categories')->inRandomOrder()->first();

        return [
            'category_id' => $this->faker->boolean(50) ? $user->categories()->inRandomOrder()->first()->id : null,
            'category_suggestion_dismissed' => null,
        ];
    }
}
