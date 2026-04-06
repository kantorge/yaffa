<?php

namespace App\Services;

use App\Contracts\InvestmentPriceProvider;
use App\Exceptions\PriceProviderException;

class InvestmentPriceProviderRegistry
{
    private array $providers = [];

    public function register(string $key, InvestmentPriceProvider $provider): void
    {
        $this->providers[$key] = $provider;
    }

    public function get(string $key): InvestmentPriceProvider
    {
        if (! isset($this->providers[$key])) {
            throw new PriceProviderException(
                "Unknown price provider: {$key}",
                $key
            );
        }

        return $this->providers[$key];
    }

    public function has(string $key): bool
    {
        return isset($this->providers[$key]);
    }

    /**
     * Get metadata for a specific provider
     *
     * @return array<string, mixed>
     *
     * @throws PriceProviderException
     */
    public function getMetadata(string $key): array
    {
        $provider = $this->get($key);

        return [
            'key' => $key,
            'name' => $provider->getName(),
            'displayName' => $provider->getDisplayName(),
            'supportsHistoricalSync' => $provider->supportsHistoricalSync(),
            'description' => $provider->getDescription(),
            'instructions' => $provider->getInstructions(),
            'investmentSettingsSchema' => $provider->getInvestmentSettingsSchema(),
            'userSettingsSchema' => $provider->getUserSettingsSchema(),
            'rateLimitPolicy' => $provider->getRateLimitPolicy(),
        ];
    }

    /**
     * Get metadata for all registered providers
     *
     * @return array<string, array<string, mixed>>
     */
    public function getAllMetadata(): array
    {
        $metadata = [];

        foreach ($this->providers as $key => $provider) {
            $metadata[$key] = [
                'key' => $key,
                'name' => $provider->getName(),
                'displayName' => $provider->getDisplayName(),
                'supportsHistoricalSync' => $provider->supportsHistoricalSync(),
                'description' => $provider->getDescription(),
                'instructions' => $provider->getInstructions(),
                'investmentSettingsSchema' => $provider->getInvestmentSettingsSchema(),
                'userSettingsSchema' => $provider->getUserSettingsSchema(),
                'rateLimitPolicy' => $provider->getRateLimitPolicy(),
            ];
        }

        return $metadata;
    }
}
