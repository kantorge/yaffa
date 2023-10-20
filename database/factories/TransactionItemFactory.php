<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\TransactionItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TransactionItemFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = TransactionItem::class;

    /**
     * Define the model's default state.
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

            'category_id' => $category->id,
            'amount' => $this->faker->numberBetween(1, 100),
            'comment' => $this->faker->boolean() ? $this->faker->text(191) : null,
        ];
    }

    /**
     * Define a state, where the category is selected for the specified user.
     */
    public function withUser(User $user): TransactionItemFactory
    {
        return $this->state(function (array $attributes) use ($user) {
            // Get a random category for the user
            // If no such category is found, then create a new parent and child category for the user
            /** @var Category $category */
            $category = Category::whereNotNull('parent_id')
                ->where('user_id', $user->id)
                ->inRandomOrder()
                ->firstOr(function () use ($user) {
                    /** @var Category $parent */
                    $parent = Category::factory()->for($user)->create();

                    return Category::factory()->for($user)->create([
                        'parent_id' => $parent->id,
                    ]);
                });

            return [
                'category_id' => $category->id,
            ];
        });
    }
}
