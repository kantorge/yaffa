<?php

namespace Database\Factories;

use App\Models\Investment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class InvestmentFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Investment::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        $user = User::has('investmentGroups')->has('currencies')->inRandomOrder()->first();

        $name = $this->faker->unique()->company();

        return [
            'name' => $name,
            'symbol' => Str::slug($name),
            'isin' => $this->faker->asciify(str_repeat('*', 12)),
            'comment' => $this->faker->boolean(25) ? $this->faker->text(191) : null,
            'active' => $this->faker->boolean(80) ? true : false,
            'auto_update' => false,
            'investment_group_id' => $user->investmentGroups()->inRandomOrder()->first()->id,
            'currency_id' => $user->currencies()->inRandomOrder()->first()->id,
            'user_id' => $user->id,
        ];
    }
}
