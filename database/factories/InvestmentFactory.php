<?php

namespace Database\Factories;

use App\Models\Currency;
use App\Models\InvestmentGroup;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class InvestmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition(): array
    {
        $name = $this->faker->unique()->company();

        $baseAttributes = [
            'name' => $name,
            'symbol' => Str::slug($name),
            'isin' => $this->faker->asciify(str_repeat('*', 12)),
            'comment' => $this->faker->boolean(25) ? $this->faker->text(191) : null,
            'active' => $this->faker->boolean(80),
            'auto_update' => false,
        ];

        // investment_group_id, currency_id and user_id must always belong to the same user.
        // Reusing an arbitrary existing user here (rather than a fresh, self-consistent one)
        // let for()/withUser() calls silently inherit another user's investment group/currency
        // - see git history for the currency-mismatch bug this caused.
        $user = User::factory()->create();

        return array_merge($baseAttributes, [
            'investment_group_id' => InvestmentGroup::factory()->for($user)->create()->id,
            'currency_id' => Currency::factory()->for($user)->create()->id,
            'user_id' => $user->id,
        ]);
    }

    /**
     * Define a state, where the related assets are created for or used from a specific user.
     *
     * Always overrides user_id/investment_group_id/currency_id to belong to $user, even if
     * definition() (or an earlier state) already set them - those earlier values are never
     * guaranteed to belong to $user, and leaving them in place would silently attach $user's
     * investment to another user's investment group/currency. Pass explicit attributes to
     * create() (not an earlier ->state() call) if you need to override a specific field -
     * create()'s attributes are always applied last and still win.
     */
    public function withUser(User $user): self
    {
        return $this->state(fn () => [
            'user_id' => $user->id,
            'investment_group_id' => $user->investmentGroups()
                ->inRandomOrder()
                ->firstOr(fn () => InvestmentGroup::factory()->for($user)->create())
                ->id,
            'currency_id' => $user->currencies()
                ->inRandomOrder()
                ->firstOr(fn () => Currency::factory()->for($user)->create())
                ->id,
        ]);
    }
}
