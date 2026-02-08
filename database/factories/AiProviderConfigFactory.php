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
        $modelsConfig = config('ai-documents.providers.'.$provider.'.models', []);
        $models = array_is_list($modelsConfig) ? $modelsConfig : array_keys($modelsConfig);
        $model = $this->faker->randomElement($models);
        $supportsVision = !array_is_list($modelsConfig)
            && (bool) ($modelsConfig[$model]['vision'] ?? false);

        return [
            'user_id' => User::factory(),
            'provider' => $provider,
            'model' => $model,
            'api_key' => 'sk-'.fake()->sha256(),
            'vision_enabled' => $supportsVision ? $this->faker->boolean() : false,
        ];
    }
}
