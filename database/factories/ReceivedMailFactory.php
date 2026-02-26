<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReceivedMailFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition(): array
    {
        return [
            'message_id' => $this->faker->uuid,
            'user_id' => User::factory(),
            'subject' => $this->faker->sentence(),
            'html' => $this->faker->randomHtml(),
            'text' => $this->faker->text(),
        ];
    }
}
