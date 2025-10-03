<?php

namespace Database\Factories;

use App\Models\ReceivedMail;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReceivedMailFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition(): array
    {
        return [
            'message_id' => $this->faker->uuid,
            'user_id' => User::inRandomOrder()->first()->id,
            'subject' => $this->faker->sentence(),
            'html' => $this->faker->randomHtml(),
            'text' => $this->faker->text(),
            'processed' => false,
            'handled' => false,
            'transaction_data' => null,
            'transaction_id' => null,
        ];
    }

    /**
     * Create a state, where the transaction_data is set to a valid transaction
     * For simplicity, we will create a new withdrawal transaction
     */
    public function withTransaction(): ReceivedMailFactory
    {
        return $this->state(function () {
            $user = User::inRandomOrder()->first();
            $transaction = Transaction::factory()
                ->for($user)
                ->withdrawal($user)
                ->create();

            $transaction->loadMissing([
                'transactionType',
                'config',
                'config.accountFrom',
                'config.accountTo'
            ]);

            return [
                'transaction_data' => [
                    'transaction_type_id' => $transaction->transaction_type_id,
                    'date' => $transaction->date->format('Y-m-d'),
                    'config_type' => $transaction->config_type,
                    'config' => [
                        'amount_from' => $transaction->config['amount_from'],
                        'amount_to' => $transaction->config['amount_to'],
                        'account_from_id' => $transaction->config['account_from_id'],
                        'account_to_id' => $transaction->config['account_to_id'],
                    ],
                    'transaction_type' => [
                        'name' => $transaction->transactionType->name,
                    ],
                    'raw' => [
                        "type" => "withdrawal",
                        "date" => $transaction->date->format('Y-m-d'),
                        "amount" => $transaction->config['amount_from'],
                        "account" => $transaction->config->accountFrom->name,
                        "payee" => $transaction->config->accountTo->name,
                    ]
                ],
                'transaction_id' => $transaction->id,
            ];
        });
    }
}
