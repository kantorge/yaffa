<?php

namespace Database\Factories;

use App\Models\Transaction;
use App\Models\TransactionDetailInvestment;
use App\Models\TransactionDetailStandard;
use App\Models\TransactionType;
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
    public function definition()
    {
        return [
            'budget' => 0,
            'schedule' => 0,
            'comment' => $this->faker->boolean(50) ? $this->faker->text(191) : null,
            'reconciled' => $this->faker->boolean(50) ? 1 : 0,
            'date' => $this->faker->dateTimeBetween('-1 year', 'now'),
        ];
    }

    /**
     * Transaction type is WITHDRAWAL
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function withdrawal()
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
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function withdrawal_schedule()
    {
        return $this->state(function (array $attributes) {
            return [
                'schedule' => 1,
                'transaction_type_id' => TransactionType::where('name', 'withdrawal')->first()->id,
                'config_type' => 'transaction_detail_standard',
                'config_id' => TransactionDetailStandard::factory()->withdrawal()->create()->id,
            ];
        });
    }

    /**
     * Transaction type is DEPOSIT
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function deposit()
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
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function transfer()
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
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function buy()
    {
        return $this->state(function (array $attributes) {
            return [
                'transaction_type_id' => TransactionType::where('name', 'buy')->first()->id,
                'config_type' => 'transaction_detail_investment',
                'config_id' => TransactionDetailInvestment::factory()->buy()->create()->id,
            ];
        });
    }
}
