<?php

namespace Database\Factories;

use App\Models\Account;
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
    public function definition(): array
    {
        // Get all account currencies and investment currencies
        // Make sure, that a common currency is used
        $accountCurrencies = Account::query()->distinct()->pluck('currency_id');
        $investmentCurrencies = Investment::query()->distinct()->pluck('currency_id');
        $currency = $accountCurrencies->intersect($investmentCurrencies)->random();

        return [
            'account_entity_id' => AccountEntity::where('config_type', 'account')
                ->whereHasMorph(
                    'config',
                    [Account::class],
                    function (Builder $query) use ($currency) {
                        $query->where('currency_id', $currency);
                    }
                )
                ->inRandomOrder()
                ->first()
                ->id,
            'investment_id' => Investment::where('currency_id', $currency)
                ->inRandomOrder()
                ->first()
                ->id,
        ];
    }

    /**
     * Transaction type is BUY
     *
     * @return Factory
     */
    public function buy(): Factory
    {
        return $this->state(function (array $attributes) {
            return [
                'price' => $this->faker->randomFloat(4, 0.0001, 100),  //TODO: dynamic based on related investment price range
                'quantity' => $this->faker->randomFloat(4, 1, 100),
                'commission' => $this->faker->randomFloat(4, 0.0001, 100),
                'tax' => $this->faker->randomFloat(4, 0.0001, 100),
                'dividend' => null,
            ];
        });
    }

    /**
     * Transaction type is SELL
     *
     * @return Factory
     */
    public function sell(): Factory
    {
        return $this->state(function (array $attributes) {
            return [
                'price' => $this->faker->randomFloat(4, 0.0001, 100),  //TODO: dynamic based on related investment price range
                'quantity' => $this->faker->randomFloat(4, 1, 100),
                'commission' => $this->faker->randomFloat(4, 0.0001, 100),
                'tax' => $this->faker->randomFloat(4, 0.0001, 100),
                'dividend' => null,
            ];
        });
    }

    /**
     * Transaction type is DIVIDEND
     *
     * @return Factory
     */
    public function dividend(): Factory
    {
        return $this->state(function (array $attributes) {
            return [
                'price' => null,
                'quantity' => null,
                'commission' => $this->faker->randomFloat(4, 0.0001, 100),
                'tax' => $this->faker->randomFloat(4, 0.0001, 100),
                'dividend' => $this->faker->randomFloat(4, 0.0001, 100),
            ];
        });
    }
}
