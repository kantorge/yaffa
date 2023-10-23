<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\AccountEntity;
use App\Models\Payee;
use App\Models\TransactionDetailStandard;
use App\Models\User;
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
    public function definition(): array
    {
        return [];
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

            return [
                'amount_from' => 0, //TODO: make dynamic
                'amount_to' => 0, //TODO: make dynamic
                'account_from_id' => $attributes['account_from_id'],
                'account_to_id' => $attributes['account_to_id'],
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
            // If the account from is not set, get one, or create a new one for the user
            // It must be an accountEntity with account type
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
            // It must be an accountEntity with payee type
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

            return [
                'amount_from' => 0, //TODO: make dynamic
                'amount_to' => 0, //TODO: make dynamic
                'account_from_id' => $attributes['account_from_id'],
                'account_to_id' => $attributes['account_to_id'],
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
                    // Exclude the account from
                    ->where('id', '!=', $attributes['account_from_id'])
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

            $amount = $this->faker->numberBetween(1, 100);

            return [
                'amount_from' => $amount,
                'amount_to' => $amount, //TODO: account for currency differencies
                'account_from_id' => $attributes['account_from_id'],
                'account_to_id' => $attributes['account_to_id'],
            ];
        });
    }
}
