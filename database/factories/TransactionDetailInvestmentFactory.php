<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\AccountEntity;
use App\Models\Currency;
use App\Models\Investment;
use App\Models\TransactionDetailInvestment;
use App\Models\User;
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
        return $this->withUser(User::factory()->create());
    }

    /**
     * Use the assets of a given user
     */
    private function withUser(User $user): array
    {
        // At this point we'll assume, that both account and investment will be taken by random
        // As a future improvement, we could account for one or the other being set, and then to create the other with the same currency

        // Get the accounts of the provided user
        $accounts = $user->accounts()->with(['config'])->get();

        // Get the investments of the provided user
        $investments = $user->investments()->get();

        // Get a list of currencies, that are used by both accounts and investments
        $currencies = $accounts->pluck('config.currency_id')->intersect($investments->pluck('currency_id'));

        // If common currencies exist, use them to get a random account and investment using the same currency
        if ($currencies->count() > 0) {
            $currency = $currencies->random();
            $account = $accounts->where('config.currency_id', $currency)->random();
            $investment = $investments->where('currency_id', $currency)->random();
        } else {
            // Get a random currency for the user, or create one if none exists
            $currency = Currency::inRandomOrder()->firstOr(fn () => Currency::factory()->create())->id;

            // Create a new account with the random currency
            $account = AccountEntity::factory()
                ->for($user)
                ->for(Account::factory()->withUser($user)->create(['currency_id' => $currency]), 'config')
                ->create();

            // Create a new investment with the random currency
            $investment = Investment::factory()
                ->for($user)
                ->create(['currency_id' => $currency]);
        }

        return [
            'account_id' => $account->id,
            'investment_id' => $investment->id,
        ];
    }

    /**
     * Transaction type is BUY
     *
     * @param User $user
     * @return Factory
     */
    public function buy(User $user): Factory
    {
        return $this->state(fn (array $attributes) => array_merge(
            [
                'price' => $this->faker->randomFloat(4, 0.0001, 100),  //TODO: dynamic based on related investment price range
                'quantity' => $this->faker->randomFloat(4, 1, 100),
                'commission' => $this->faker->randomFloat(4, 0.0001, 100),
                'tax' => $this->faker->randomFloat(4, 0.0001, 100),
                'dividend' => null,
            ],
            $this->withUser($user)
        ));
    }

    /**
     * Transaction type is SELL
     *
     * @param User $user
     * @return Factory
     */
    public function sell(User $user): Factory
    {
        return $this->state(fn (array $attributes) => array_merge(
            [
                'price' => $this->faker->randomFloat(4, 0.0001, 100),  //TODO: dynamic based on related investment price range
                'quantity' => $this->faker->randomFloat(4, 1, 100),
                'commission' => $this->faker->randomFloat(4, 0.0001, 100),
                'tax' => $this->faker->randomFloat(4, 0.0001, 100),
                'dividend' => null,
            ],
            $this->withUser($user)
        ));
    }

    /**
     * Transaction type is DIVIDEND
     *
     * @param User $user
     * @return Factory
     */
    public function dividend(User $user): Factory
    {
        return $this->state(fn (array $attributes) => array_merge(
            [
                'price' => null,
                'quantity' => null,
                'commission' => $this->faker->randomFloat(4, 0.0001, 100),
                'tax' => $this->faker->randomFloat(4, 0.0001, 100),
                'dividend' => $this->faker->randomFloat(4, 0.0001, 100),
            ],
            $this->withUser($user)
        ));
    }
}
