<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\InvestmentProviderConfig>
 */
class InvestmentProviderConfigFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'provider_key' => 'alpha_vantage',
            'credentials' => [
                'api_key' => 'demo-' . fake()->sha1(),
            ],
            'options' => null,
            'enabled' => true,
            'last_error' => null,
            'plan' => 'free',
            'rate_limit_overrides' => null,
        ];
    }
}
