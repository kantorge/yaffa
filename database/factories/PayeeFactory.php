<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Payee;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PayeeFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Payee::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'category_suggestion_dismissed' => null,
        ];
    }

    /**
     * Define a state, where the related assets are created for or used from a specific user.
     */
    public function withUser(User $user): self
    {
        return $this->state(function (array $attributes) use ($user) {
            // If the category is not set, get one, or create a new one for the user
            if (! isset($attributes['category_id'])) {
                $attributes['category_id'] = $user->categories()
                    ->inRandomOrder()
                    ->firstOr(fn () => Category::factory()->for($user)->create())
                    ->id;
            }

            return $attributes;
        });
    }
}
