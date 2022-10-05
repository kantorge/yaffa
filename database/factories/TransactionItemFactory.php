<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\TransactionItem;
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
     *
     * @return array
     */
    public function definition()
    {
        return [
            'category_id' => Category::whereNotNull('parent_id')->inRandomOrder()->first()->id,
            'amount' => $this->faker->numberBetween(1, 100),
            'comment' => $this->faker->boolean(50) ? $this->faker->text(191) : null,
        ];
    }
}
