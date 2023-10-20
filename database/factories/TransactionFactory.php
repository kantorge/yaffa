<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\AccountEntity;
use App\Models\Investment;
use App\Models\Payee;
use App\Models\Transaction;
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

                $transaction->update([
                    'amount_primary' => $transaction->transactionItems->sum('amount_primary'),
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

                $transaction->update([
                    'amount_primary' => $transaction->transactionItems->sum('amount_primary'),
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
     */
    public function definition(): array
    {
        return [
            'budget' => 0,
            'schedule' => 0,
            'comment' => $this->faker->boolean(50) ? $this->faker->text(191) : null,
            'reconciled' => $this->faker->boolean(50) ? 1 : 0,
            'date' => $this->faker->dateTimeBetween('-1 year', 'now'),
            'user_id' => null,
        ];
    }

    /**
     * Transaction type is WITHDRAWAL
     */
    public function withdrawal(User $user): TransactionFactory
    {
        return $this->state(function (array $attributes) use ($user) {
            // If the account from is not set, get one, or create a new one for the user
            // It must be an accountEntity with account type
            if (! isset($attributes['account_from_id'])) {
                $attributes['account_from_id'] = $user->accounts()
                    ->inRandomOrder()
                    ->firstOr(fn () => AccountEntity::factory()
                        ->for($user)
                        ->for(
                            Account::factory()->withUser($user),
                            'config'
                        )
                        ->create())
                    ->id;
            }

            // If the account to is not set, get one, or create a new one for the user
            // It must be an accountEntity with payee type
            if (! isset($attributes['account_to_id'])) {
                $attributes['account_to_id'] = $user->payees()
                    ->inRandomOrder()
                    ->firstOr(fn () => AccountEntity::factory()
                        ->for($user)
                        ->for(
                            Payee::factory()->withUser($user),
                            'config'
                        )
                        ->create())
                    ->id;
            }

            // Merge and use the passed attributes as Transaction attributes
            // If not passed, leave it empty to use factory defaults
            return array_merge(
                $attributes,
                [
                    'user_id' => $user->id,
                    'transaction_type_id' => TransactionType::where('name', 'withdrawal')->first()->id,
                    'amount_primary' => 0, //TODO: make dynamic
                    'amount_secondary' => 0, //TODO: make dynamic
                    'account_from_id' => $attributes['account_from_id'],
                    'account_to_id' => $attributes['account_to_id'],
                ]
            );
        });
    }

    /**
     * Transaction type is WITHDRAWAL and has SCHEDULE
     */
    public function withdrawal_schedule(User $user): Factory
    {
        return $this->state(function (array $attributes) use ($user) {
            // If the account from is not set, get one, or create a new one for the user
            // It must be an accountEntity with account type
            if (! isset($attributes['account_from_id'])) {
                $attributes['account_from_id'] = $user->accounts()
                    ->inRandomOrder()
                    ->firstOr(fn () => AccountEntity::factory()
                        ->for($user)
                        ->for(
                            Account::factory()->withUser($user),
                            'config'
                        )
                        ->create())
                    ->id;
            }

            // If the account to is not set, get one, or create a new one for the user
            // It must be an accountEntity with payee type
            if (! isset($attributes['account_to_id'])) {
                $attributes['account_to_id'] = $user->payees()
                    ->inRandomOrder()
                    ->firstOr(fn () => AccountEntity::factory()
                        ->for($user)
                        ->for(
                            Payee::factory()->withUser($user),
                            'config'
                        )
                        ->create())
                    ->id;
            }

            // Merge and use the passed attributes as Transaction attributes
            // If not passed, leave it empty to use factory defaults
            return array_merge(
                $attributes,
                [
                    'user_id' => $user->id,
                    'schedule' => 1,
                    'date' => null,
                    'transaction_type_id' => TransactionType::where('name', 'withdrawal')->first()->id,
                    'amount_primary' => 0, //TODO: make dynamic
                    'amount_secondary' => null,
                    'account_from_id' => $attributes['account_from_id'],
                    'account_to_id' => $attributes['account_to_id'],
                ]
            );
        });
    }

    /**
     * Transaction type is DEPOSIT
     */
    public function deposit(User $user): Factory
    {
        return $this->state(function (array $attributes) use ($user) {
            // If the account from is not set, get one, or create a new one for the user
            // It must be an accountEntity with payee type
            if (! isset($attributes['account_from_id'])) {
                $attributes['account_from_id'] = $user->payees()
                    ->inRandomOrder()
                    ->firstOr(fn () => AccountEntity::factory()
                        ->for($user)
                        ->for(
                            Payee::factory()->withUser($user),
                            'config'
                        )
                        ->create())
                    ->id;
            }

            // If the account to is not set, get one, or create a new one for the user
            // It must be an accountEntity with account type
            if (! isset($attributes['account_to_id'])) {
                $attributes['account_to_id'] = $user->accounts()
                    ->inRandomOrder()
                    ->firstOr(fn () => AccountEntity::factory()
                        ->for($user)
                        ->for(
                            Account::factory()->withUser($user),
                            'config'
                        )
                        ->create())
                    ->id;
            }

            // Merge and use the passed attributes as Transaction attributes
            // If not passed, leave it empty to use factory defaults
            return array_merge(
                $attributes,
                [
                    'user_id' => $user->id,
                    'transaction_type_id' => TransactionType::where('name', 'deposit')->first()->id,
                    'amount_primary' => 0, //TODO: make dynamic
                    'amount_secondary' => null,
                    'account_from_id' => $attributes['account_from_id'],
                    'account_to_id' => $attributes['account_to_id'],
                ]
            );
        });
    }

    /**
     * Transaction type is TRANSFER
     */
    // TODO: account for cases with same and different currencies
    public function transfer(User $user): Factory
    {
        return $this->state(function (array $attributes) use ($user) {
            // If the account from is not set, get one, or create a new one for the user
            // It must be an accountEntity with account type
            if (! isset($attributes['account_from_id'])) {
                $attributes['account_from_id'] = $user->accounts()
                    ->inRandomOrder()
                    ->firstOr(fn () => AccountEntity::factory()
                        ->for($user)
                        ->for(
                            Account::factory()->withUser($user),
                            'config'
                        )
                        ->create())
                    ->id;
            }

            // If the account to is not set, get one, or create a new one for the user
            // It must be an accountEntity with account type
            if (! isset($attributes['account_to_id'])) {
                $attributes['account_to_id'] = $user->accounts()
                    ->inRandomOrder()
                    ->firstOr(fn () => AccountEntity::factory()
                        ->for($user)
                        ->for(
                            Account::factory()->withUser($user),
                            'config'
                        )
                        ->create())
                    ->id;
            }

            // Merge and use the passed attributes as Transaction attributes
            // If not passed, leave it empty to use factory defaults
            return array_merge(
                $attributes,
                [
                    'user_id' => $user->id,
                    'transaction_type_id' => TransactionType::where('name', 'transfer')->first()->id,
                    'amount_primary' => 0, //TODO: make dynamic
                    'amount_secondary' => 0, //TODO: make dynamic
                    'account_from_id' => $attributes['account_from_id'],
                    'account_to_id' => $attributes['account_to_id'],
                ]
            );
        });
    }

    /**
     * Transaction type is BUY investment
     */
    public function buy(User $user): Factory
    {
        return $this->state(function (array $attributes) use ($user) {
            // If the account from is not set, get one, or create a new one for the user
            // It must be an accountEntity with account type
            if (! isset($attributes['account_from_id'])) {
                $attributes['account_from_id'] = $user->accounts()
                    ->inRandomOrder()
                    ->firstOr(fn () => AccountEntity::factory()
                        ->for($user)
                        ->for(
                            Account::factory()->withUser($user),
                            'config'
                        )
                        ->create())
                    ->id;
            }

            // If the account to is not set, get one, or create a new one for the user
            // It must be an accountEntity with investment type
            if (! isset($attributes['account_to_id'])) {
                $attributes['account_to_id'] = $user->accounts()
                    ->inRandomOrder()
                    ->firstOr(fn () => AccountEntity::factory()
                        ->for($user)
                        ->for(
                            Investment::factory()->withUser($user),
                            'config'
                        )
                        ->create())
                    ->id;
            }

            // Merge and use the passed attributes as Transaction attributes
            // If not passed, leave it empty to use factory defaults
            return array_merge(
                $attributes,
                [
                    'user_id' => $user->id,
                    'transaction_type_id' => TransactionType::where('name', 'transfer')->first()->id,
                    'amount_primary' => null,
                    'amount_secondary' => null,
                    'account_from_id' => $attributes['account_from_id'],
                    'account_to_id' => $attributes['account_to_id'],
                    'price' => $this->faker->randomFloat(4, 0.0001, 100),  //TODO: dynamic based on related investment price range
                    'quantity' => $this->faker->randomFloat(4, 1, 100),
                    'commission' => $this->faker->randomFloat(4, 0.0001, 100),
                    'tax' => $this->faker->randomFloat(4, 0.0001, 100),
                ]
            );
        });
    }

    /**
     * Transaction type is SELL investment
     */
    public function sell(User $user): Factory
    {
        return $this->state(function (array $attributes) use ($user) {
            // If the account from is not set, get one, or create a new one for the user
            // It must be an accountEntity with investment type
            if (! isset($attributes['account_from_id'])) {
                $attributes['account_from_id'] = $user->accounts()
                    ->inRandomOrder()
                    ->firstOr(fn () => AccountEntity::factory()
                        ->for($user)
                        ->for(
                            Investment::factory()->withUser($user),
                            'config'
                        )
                        ->create())
                    ->id;
            }

            // If the account to is not set, get one, or create a new one for the user
            // It must be an accountEntity with account type
            if (! isset($attributes['account_to_id'])) {
                $attributes['account_to_id'] = $user->accounts()
                    ->inRandomOrder()
                    ->firstOr(fn () => AccountEntity::factory()
                        ->for($user)
                        ->for(
                            Account::factory()->withUser($user),
                            'config'
                        )
                        ->create())
                    ->id;
            }

            // Merge and use the passed attributes as Transaction attributes
            // If not passed, leave it empty to use factory defaults
            return array_merge(
                $attributes,
                [
                    'user_id' => $user->id,
                    'transaction_type_id' => TransactionType::where('name', 'transfer')->first()->id,
                    'amount_primary' => null,
                    'amount_secondary' => null,
                    'account_from_id' => $attributes['account_from_id'],
                    'account_to_id' => $attributes['account_to_id'],
                    'price' => $this->faker->randomFloat(4, 0.0001, 100),  //TODO: dynamic based on related investment price range
                    'quantity' => $this->faker->randomFloat(4, 1, 100), // TODO: account for available quantity
                    'commission' => $this->faker->randomFloat(4, 0.0001, 100),
                    'tax' => $this->faker->randomFloat(4, 0.0001, 100),
                ]
            );
        });
    }

    /**
     * Transaction type is DIVIDEND investment
     */
    public function dividend(User $user): Factory
    {
        return $this->state(function (array $attributes) use ($user) {
            // If the account from is not set, get one, or create a new one for the user
            // It must be an accountEntity with investment type
            if (! isset($attributes['account_from_id'])) {
                $attributes['account_from_id'] = $user->accounts()
                    ->inRandomOrder()
                    ->firstOr(fn () => AccountEntity::factory()
                        ->for($user)
                        ->for(
                            Investment::factory()->withUser($user),
                            'config'
                        )
                        ->create())
                    ->id;
            }

            // If the account to is not set, get one, or create a new one for the user
            // It must be an accountEntity with account type
            if (! isset($attributes['account_to_id'])) {
                $attributes['account_to_id'] = $user->accounts()
                    ->inRandomOrder()
                    ->firstOr(fn () => AccountEntity::factory()
                        ->for($user)
                        ->for(
                            Account::factory()->withUser($user),
                            'config'
                        )
                        ->create())
                    ->id;
            }

            // Merge and use the passed attributes as Transaction attributes
            // If not passed, leave it empty to use factory defaults
            return array_merge(
                $attributes,
                [
                    'user_id' => $user->id,
                    'transaction_type_id' => TransactionType::where('name', 'transfer')->first()->id,
                    'amount_primary' => $this->faker->randomFloat(4, 0.0001, 100),
                    'amount_secondary' => null,
                    'account_from_id' => $attributes['account_from_id'],
                    'account_to_id' => $attributes['account_to_id'],
                    'price' => null,
                    'quantity' => null,
                    'commission' => $this->faker->randomFloat(4, 0.0001, 100),
                    'tax' => $this->faker->randomFloat(4, 0.0001, 100),
                ]
            );
        });
    }
}
