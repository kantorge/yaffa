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
     */
    public function definition(): array
    {
        $user = User::inRandomOrder()->firstOr(fn () => User::factory()->create());

        return [
            'name' => $this->faker->unique()->text(mt_rand(5, 25)),
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
