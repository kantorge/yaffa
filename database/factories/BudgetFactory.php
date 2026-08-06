<?php

namespace Database\Factories;

use App\Enums\TransactionType as TransactionTypeEnum;
use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Budget>
 */
class BudgetFactory extends Factory
{
    public function definition(): array
    {
        $user = User::factory()->create();

        return [
            'user_id' => $user->id,
            'category_id' => Category::factory()->create(['user_id' => $user->id])->id,
            'account_id' => null,
            'transaction_type' => $this->faker->randomElement([
                TransactionTypeEnum::WITHDRAWAL->value,
                TransactionTypeEnum::DEPOSIT->value,
            ]),
            'amount' => $this->faker->randomFloat(2, 10, 1000),
            'comment' => null,
            'frequency' => $this->faker->randomElement(['DAILY', 'WEEKLY', 'MONTHLY', 'YEARLY']),
            'interval' => $this->faker->numberBetween(1, 3),
            'start_date' => $this->faker->dateTimeBetween('-1 year', 'now'),
            'end_date' => null,
            'count' => null,
            'inflation' => null,
        ];
    }
}
