<?php

namespace Database\Factories;

use App\Models\Transaction;
use App\Models\TransactionDetailInvestment;
use App\Models\TransactionDetailStandard;
use App\Models\TransactionType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TransactionFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Transaction::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition(): array
    {
        return [
            'budget' => 0,
            'schedule' => 0,
            'comment' => $this->faker->boolean(50) ? $this->faker->text(191) : null,
            'reconciled' => $this->faker->boolean(50) ? 1 : 0,
            'date' => $this->faker->dateTimeBetween('-1 year', 'now'),
            'user_id' => User::inRandomOrder()->first()->id,
        ];
    }

    /**
     * Transaction type is WITHDRAWAL
     *
     * @return Factory
     */
    public function withdrawal(): Factory
    {
        return $this->state(function (array $attributes) {
            return [
                'transaction_type_id' => TransactionType::where('name', 'withdrawal')->first()->id,
                'config_type' => 'transaction_detail_standard',
                'config_id' => TransactionDetailStandard::factory()->withdrawal()->create()->id,
            ];
        });
    }

    /**
     * Transaction type is WITHDRAWAL and has SCHEDULE
     *
     * @return Factory
     */
    public function withdrawal_schedule(): Factory
    {
        return $this->state(function (array $attributes) {
            return [
                'date' => null,
                'schedule' => 1,
                'budget' => 0,
                'reconciled' => 0,
                'transaction_type_id' => TransactionType::where('name', 'withdrawal')->first()->id,
                'config_type' => 'transaction_detail_standard',
                'config_id' => TransactionDetailStandard::factory()->withdrawal()->create()->id,
            ];
        });
    }

    /**
     * Transaction type is DEPOSIT
     *
     * @return Factory
     */
    public function deposit(): Factory
    {
        return $this->state(function (array $attributes) {
            return [
                'transaction_type_id' => TransactionType::where('name', 'deposit')->first()->id,
                'config_type' => 'transaction_detail_standard',
                'config_id' => TransactionDetailStandard::factory()->deposit()->create()->id,
            ];
        });
    }

    /**
     * Transaction type is TRANSFER
     *
     * @return Factory
     */
    public function transfer(): Factory
    {
        return $this->state(function (array $attributes) {
            return [
                'transaction_type_id' => TransactionType::where('name', 'transfer')->first()->id,
                'config_type' => 'transaction_detail_standard',
                'config_id' => TransactionDetailStandard::factory()->transfer()->create()->id,
            ];
        });
    }

    /**
     * Transaction type is BUY investment
     *
     * @param array $config
     * @return Factory
     */
    public function buy(array $config = []): Factory
    {
        return $this->state(function (array $attributes) use ($config) {
            return [
                'transaction_type_id' => TransactionType::where('name', 'Buy')->first()->id,
                'config_type' => 'transaction_detail_investment',
                'config_id' => TransactionDetailInvestment::factory()
                    ->buy()->create($config)->id,
            ];
        });
    }

    /**
     * Transaction type is SELL investment
     *
     * @param array $config
     * @return Factory
     */
    public function sell(array $config = []): Factory
    {
        return $this->state(function (array $attributes) use ($config) {
            return [
                'transaction_type_id' => TransactionType::where('name', 'Sell')->first()->id,
                'config_type' => 'transaction_detail_investment',
                'config_id' => TransactionDetailInvestment::factory()
                    ->sell()->create($config)->id,
            ];
        });
    }

    /**
     * Transaction type is DIVIDEND investment
     *
     * @param array $config
     * @return Factory
     */
    public function dividend(array $config = []): Factory
    {
        return $this->state(function (array $attributes) use ($config) {
            return [
                'transaction_type_id' => TransactionType::where('name', 'Dividend')->first()->id,
                'config_type' => 'transaction_detail_investment',
                'config_id' => TransactionDetailInvestment::factory()
                    ->dividend()->create($config)->id,
            ];
        });
    }
}
