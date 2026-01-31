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
        $providers = array_keys(config('ai-documents.providers', []));
        $provider = $this->faker->randomElement($providers);
        $models = config('ai-documents.providers.'.$provider.'.models', []);
        $model = $this->faker->randomElement($models);

        return [
            'user_id' => User::factory(),
            'provider' => $provider,
            'model' => $model,
            'api_key' => 'sk-'.fake()->sha256(),
        ];
    }
}
