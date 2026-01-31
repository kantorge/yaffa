<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AiProviderConfig>
 */
class AiProviderConfigFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'provider' => $this->faker->randomElement(['openai', 'gemini']),
            'model' => 'gpt-4o-mini',
            'api_key' => 'sk-' . fake()->sha256(),
        ];
    }
}
