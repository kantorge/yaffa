<?php

namespace Database\Factories;

use App\Models\ReceivedMail;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReceivedMailFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = ReceivedMail::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition(): array
    {
        return [
            'message_id' => $this->faker->uuid,
            'user_id' => User::inRandomOrder()->first()->id,
            'subject' => $this->faker->sentence(),
            'html' => $this->faker->randomHtml(),
            'text' => $this->faker->text(),
            'processed' => false,
            'handled' => false,
            'transaction_data' => null,
            'transaction_id' => null,
        ];
    }
}
