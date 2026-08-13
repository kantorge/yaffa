<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\AccountEntity;
use App\Models\Currency;
use App\Models\Investment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TransactionDetailInvestmentFactory extends Factory
{
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
     * Use the assets of a given user.
     *
     * @param array $configAttributes Attributes the caller will ultimately override with
     *   (e.g. a specific investment_id/account_id pinned by TransactionFactory::buy_schedule()).
     *   When present, the other side is resolved to match its currency instead of being
     *   picked independently at random - otherwise the two can end up in different
     *   currencies and MoneyCast's currency-mismatch guard (price vs dividend/tax/commission)
     *   throws, since nothing else in the app enforces this invariant yet (see
     *   TransactionRequest's "TODO: validate currency of account and investment").
     */
    private function withUser(User $user, array $configAttributes = []): array
    {
        if (isset($configAttributes['investment_id'])) {
            return $this->withInvestment($user, Investment::findOrFail($configAttributes['investment_id']));
        }

        if (isset($configAttributes['account_id'])) {
            return $this->withAccount($user, AccountEntity::findOrFail($configAttributes['account_id']));
        }

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
            $currency = Currency::inRandomOrder()->firstOr(fn () => Currency::factory()->for($user)->create())->id;

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
     * Resolve an account for the given user that shares the investment's currency,
     * reusing an existing one where possible instead of always creating a new one.
     */
    private function withInvestment(User $user, Investment $investment): array
    {
        $account = $user->accounts()
            ->with('config')
            ->get()
            ->first(fn (AccountEntity $account) => $account->config?->currency_id === $investment->currency_id);

        $account ??= AccountEntity::factory()
            ->for($user)
            ->for(Account::factory()->withUser($user)->create(['currency_id' => $investment->currency_id]), 'config')
            ->create();

        return [
            'account_id' => $account->id,
            'investment_id' => $investment->id,
        ];
    }

    /**
     * Resolve an investment for the given user that shares the account's currency,
     * reusing an existing one where possible instead of always creating a new one.
     */
    private function withAccount(User $user, AccountEntity $accountEntity): array
    {
        $currencyId = $accountEntity->config->currency_id;

        $investment = $user->investments()->where('currency_id', $currencyId)->inRandomOrder()->first()
            ?? Investment::factory()->for($user)->create(['currency_id' => $currencyId]);

        return [
            'account_id' => $accountEntity->id,
            'investment_id' => $investment->id,
        ];
    }

    /**
     * Transaction type is BUY
     *
     * @param User $user
     * @return Factory
     */
    public function buy(User $user, array $configAttributes = []): Factory
    {
        return $this->state(fn (array $attributes) => array_merge(
            [
                'price' => $this->faker->randomFloat(4, 0.0001, 100),  //TODO: dynamic based on related investment price range
                'quantity' => $this->faker->randomFloat(4, 1, 100),
                'commission' => $this->faker->randomFloat(4, 0.0001, 100),
                'tax' => $this->faker->randomFloat(4, 0.0001, 100),
                'dividend' => null,
            ],
            $this->withUser($user, $configAttributes)
        ));
    }

    /**
     * Transaction type is SELL
     *
     * @param User $user
     * @return Factory
     */
    public function sell(User $user, array $configAttributes = []): Factory
    {
        return $this->state(fn (array $attributes) => array_merge(
            [
                'price' => $this->faker->randomFloat(4, 0.0001, 100),  //TODO: dynamic based on related investment price range
                'quantity' => $this->faker->randomFloat(4, 1, 100),
                'commission' => $this->faker->randomFloat(4, 0.0001, 100),
                'tax' => $this->faker->randomFloat(4, 0.0001, 100),
                'dividend' => null,
            ],
            $this->withUser($user, $configAttributes)
        ));
    }

    /**
     * Transaction type is DIVIDEND
     *
     * @param User $user
     * @return Factory
     */
    public function dividend(User $user, array $configAttributes = []): Factory
    {
        return $this->state(fn (array $attributes) => array_merge(
            [
                'price' => null,
                'quantity' => null,
                'commission' => $this->faker->randomFloat(4, 0.0001, 100),
                'tax' => $this->faker->randomFloat(4, 0.0001, 100),
                'dividend' => $this->faker->randomFloat(4, 0.0001, 100),
            ],
            $this->withUser($user, $configAttributes)
        ));
    }
}
