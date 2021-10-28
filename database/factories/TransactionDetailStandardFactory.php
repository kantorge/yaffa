<?php

namespace Database\Factories;

use App\Models\AccountEntity;
use App\Models\TransactionDetailStandard;
use Illuminate\Database\Eloquent\Factories\Factory;

class TransactionDetailStandardFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = TransactionDetailStandard::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [];
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
                'amount_from' => 0, //TODO: make dynamic
                'amount_to' => 0, //TODO: make dynamic
                'account_from_id' => AccountEntity::where('config_type', 'account')->inRandomOrder()->first()->id,
                'account_to_id' => AccountEntity::where('config_type', 'payee')->inRandomOrder()->first()->id,
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
                'amount_from' => 0, //TODO: make dynamic
                'amount_to' => 0, //TODO: make dynamic
                'account_from_id' => AccountEntity::where('config_type', 'payee')->inRandomOrder()->first()->id,
                'account_to_id' => AccountEntity::where('config_type', 'account')->inRandomOrder()->first()->id,
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
            $accounts = AccountEntity::where('config_type', 'account')->inRandomOrder()->take(2)->get();
            $amount = $this->faker->numberBetween($min = 1, $max = 100);

            return [
                'amount_from' => $amount,
                'amount_to' => $amount, //TODO: account for currency differencies
                'account_from_id' => $accounts->slice(0, 1)->first()->id,
                'account_to_id' => $accounts->slice(1, 1)->first()->id,
            ];
        });
    }
}
