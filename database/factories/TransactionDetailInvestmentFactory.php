<?php

namespace Database\Factories;

use App\Models\AccountEntity;
use App\Models\Investment;
use App\Models\TransactionDetailInvestment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;

class TransactionDetailInvestmentFactory extends Factory
{

    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = TransactionDetailInvestment::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        //TODO: random account and investment with same currency
        return [
            "account_id" => AccountEntity::where('config_type', 'account')
                ->whereHasMorph(
                    'config',
                    [\App\Models\Account::class],
                    function (Builder $query) {
                        $query->where('currency_id', 1);
                    }
                )
                ->inRandomOrder()
                ->first()
                ->id,
            "investment_id" => Investment::where('currency_id', 1)
                ->inRandomOrder()
                ->first()
                ->id,
        ];
    }

    /**
     * Transaction type is BUY
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function buy()
    {
        return $this->state(function (array $attributes) {
            return [
                "price" => $this->faker->randomFloat(4, 0.0001, 100),  //TODO: dynamic based on related investment price
                "quantity" => $this->faker->randomFloat(4, 1, 100),
                "commission" => $this->faker->randomFloat(4, 0.0001, 100),
                "dividend" => 0,
            ];
        });
    }
}
