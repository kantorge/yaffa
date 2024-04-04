<?php

namespace Database\Factories;

use App\Models\Transaction;
use App\Models\TransactionDetailInvestment;
use App\Models\TransactionDetailStandard;
use App\Models\TransactionItem;
use App\Models\TransactionSchedule;
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
     * Configure the model factory.
     *
     * @return $this
     */
    public function configure()
    {
        return $this->afterCreating(function (Transaction $transaction) {
            // Get the tags of the user for later use
            $tags = $transaction->user->tags;

            // Ensure that a schedule is created for scheduled or budgeted transactions
            if ($transaction->schedule || $transaction->budget) {
                TransactionSchedule::factory()
                    ->for($transaction)
                    ->create();
            }

            // If the transaction is a withdrawal, then create a random number of transaction items,
            // and update the transaction amount to be the sum of the transaction items.
            if ($transaction->transactionType->name === 'withdrawal') {
                $transaction->transactionItems()
                    ->createMany(
                        TransactionItem::factory()
                            ->withUser($transaction->user)
                            ->count(rand(1, 5))
                            ->make()
                            ->toArray()
                    );

                $transaction->config->update([
                    'amount_from' => $transaction->transactionItems->sum('amount_primary'),
                    'amount_to' => $transaction->transactionItems->sum('amount_primary'),
                ]);

                // Attach tags of the same user to some of the newly created transaction items
                if ($tags->count() > 0) {
                    $transaction->transactionItems()
                        ->inRandomOrder()
                        ->limit(rand(1, 3))
                        ->get()
                        ->each(function ($transactionItem) use ($tags) {
                            $transactionItem->tags()->attach(
                                $tags->random(rand(1, 3))->pluck('id')->toArray()
                            );
                        });
                }
            }

            // If the transaction is a deposit, then create one transaction item,
            // and update the transaction amount to be the sum of the transaction items.
            if ($transaction->transactionType->name === 'deposit') {
                $transaction->transactionItems()
                    ->create(
                        TransactionItem::factory()
                            ->withUser($transaction->user)
                            ->make()
                            ->toArray()
                    );

                $transaction->config->update([
                    'amount_from' => $transaction->transactionItems->sum('amount_primary'),
                    'amount_to' => $transaction->transactionItems->sum('amount_primary'),
                ]);

                // With a 25% chance, attach tags of the same user to the newly created transaction item
                if ($tags->count() > 0) {
                    if ($this->faker->boolean(25)) {
                        $transaction->transactionItems()
                            ->first()
                            ->tags()
                            ->attach(
                                $tags->random(rand(1, 3))->pluck('id')->toArray()
                            );
                    }
                }
            }
        });
    }

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition(): array
    {
        return [
            'budget' => false,
            'schedule' => false,
            'comment' => $this->faker->boolean() ? $this->faker->text(191) : null,
            'reconciled' => $this->faker->boolean(),
            'date' => $this->faker->dateTimeBetween('-1 year', 'now'),
            'user_id' => User::factory(),
        ];
    }

    /**
     * Transaction type is WITHDRAWAL
     *
     * @param User $user
     * @return Factory
     */
    public function withdrawal(User $user): Factory
    {
        return $this->state(function (array $attributes) use ($user) {
            return [
                'transaction_type_id' => TransactionType::where('name', 'withdrawal')->first()->id,
                'config_type' => 'standard',
                'config_id' => TransactionDetailStandard::factory()->withdrawal($user)->create(),
            ];
        });
    }

    /**
     * Transaction type is WITHDRAWAL and has SCHEDULE
     *
     * @param User $user
     * @return Factory
     */
    public function withdrawal_schedule(User $user): Factory
    {
        return $this->state(function (array $attributes) use ($user) {
            return [
                'date' => null,
                'schedule' => 1,
                'budget' => 0,
                'reconciled' => 0,
                'transaction_type_id' => TransactionType::where('name', 'withdrawal')->first()->id,
                'config_type' => 'standard',
                'config_id' => TransactionDetailStandard::factory()->withdrawal($user)->create(),
            ];
        });
    }

    /**
     * Transaction type is DEPOSIT
     *
     * @param User $user
     * @return Factory
     */
    public function deposit(User $user): Factory
    {
        return $this->state(function (array $attributes) use ($user) {
            return [
                'transaction_type_id' => TransactionType::where('name', 'deposit')->first()->id,
                'config_type' => 'standard',
                'config_id' => TransactionDetailStandard::factory()->deposit($user)->create(),
            ];
        });
    }

    /**
     * Transaction type is TRANSFER
     *
     * @param User $user
     * @return Factory
     */
    public function transfer(User $user): Factory
    {
        return $this->state(function (array $attributes) use ($user) {
            return [
                'transaction_type_id' => TransactionType::where('name', 'transfer')->first()->id,
                'config_type' => 'standard',
                'config_id' => TransactionDetailStandard::factory()->transfer($user)->create(),
            ];
        });
    }

    /**
     * Transaction type is BUY investment
     *
     * @param User $user
     * @param array $configAttributes
     * @return Factory
     */
    public function buy(User $user, array $configAttributes = []): Factory
    {
        return $this->state(function (array $attributes) use ($user, $configAttributes) {
            return [
                'transaction_type_id' => TransactionType::where('name', 'Buy')->first()->id,
                'config_type' => 'investment',
                'config_id' => TransactionDetailInvestment::factory()->buy($user)->create($configAttributes),
            ];
        });
    }

    /**
     * Transaction type is SELL investment
     *
     * @param User $user
     * @param array $configAttributes
     * @return Factory
     */
    public function sell(User $user, array $configAttributes = []): Factory
    {
        return $this->state(function (array $attributes) use ($user, $configAttributes) {
            return [
                'transaction_type_id' => TransactionType::where('name', 'Sell')->first()->id,
                'config_type' => 'investment',
                'config_id' => TransactionDetailInvestment::factory()->sell($user)->create($configAttributes),
            ];
        });
    }

    /**
     * Transaction type is DIVIDEND investment
     *
     * @param User $user
     * @param array $configAttributes
     * @return Factory
     */
    public function dividend(User $user, array $configAttributes): Factory
    {
        return $this->state(function (array $attributes) use ($user, $configAttributes) {
            return [
                'transaction_type_id' => TransactionType::where('name', 'Dividend')->first()->id,
                'config_type' => 'investment',
                'config_id' => TransactionDetailInvestment::factory()->dividend($user)->create($configAttributes),
            ];
        });
    }
}
