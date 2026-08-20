<?php

namespace Database\Factories;

use App\Enums\TransactionType as TransactionTypeEnum;
use App\Models\Budget;
use App\Models\Category;
use App\Models\Currency;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Budget>
 */
class BudgetFactory extends Factory
{
    /**
     * amount (MoneyCast) resolves its currency via Budget::currency(), which falls back to
     * the owning user's base currency when account_id is null - ensure one exists so a bare
     * `Budget::factory()->create(['user_id' => $user->id])` doesn't throw the first time
     * amount is read, in tests that don't otherwise set up a currency for that user.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (Budget $budget) {
            if ($budget->account_id === null && $budget->user->baseCurrency() === null) {
                Currency::factory()->for($budget->user)->create(['base' => true]);
            }
        });
    }

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
