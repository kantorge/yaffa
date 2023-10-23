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
     *
     * @return array
     */
    public function definition(): array
    {
        // This will select a category that is not a parent category, but it belongs to a random user
        // If no such category is found, then take a random user, and create a new parent and child category for them
        // Users are assumed to exist at this point
        /** @var Category $category */
        $category = Category::whereNotNull('parent_id')
            ->inRandomOrder()
            ->firstOr(function () {
                $user = User::inRandomOrder()->first();
                /** @var Category $parent */
                $parent = Category::factory()->for($user)->create(['parent_id' => null]);

                return Category::factory()->for($user)->create(['parent_id' => $parent->id]);
            });

        return [
            'category_id' => $this->faker->boolean(50) ? $category->id : null,
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
                    ->childCategory()
                    ->inRandomOrder()
                    ->firstOr(function () use ($user) {
                        /** @var Category $parent */
                        $parent = Category::factory()->for($user)->create();

                        return Category::factory()
                            ->for($user)
                            ->for($parent, 'parent')
                            ->create();
                    })
                    ->id;
            }

            return $attributes;
        });
    }
}
