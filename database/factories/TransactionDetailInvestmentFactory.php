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
        //TODO: investment és account egy currency-ből legyen, random választással
        return [
            //"account_id" => AccountEntity::where('config_type', 'account')->inRandomOrder()->first()->id,
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
                "price" => $this->faker->randomFloat($nbMaxDecimals = 4, $min = 0.0001, $max = 100),  //TODO: dynamic based on investment price
                "quantity" => $this->faker->randomFloat($nbMaxDecimals = 4, $min = 1, $max = 100),
                "commission" => $this->faker->randomFloat($nbMaxDecimals = 4, $min = 0.0001, $max = 100),
                "dividend" => 0,
            ];
        });
    }
}
