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
     * @return array{name: string, displayName: string, refillAvailable: bool, description: string, instructions: string}
     *
     * @throws PriceProviderException
     */
    public function getMetadata(string $key): array
    {
        $provider = $this->get($key);

        return [
            'name' => $provider->getName(),
            'displayName' => $provider->getDisplayName(),
            'refillAvailable' => $provider->supportsRefill(),
            'description' => $provider->getDescription(),
            'instructions' => $provider->getInstructions(),
        ];
    }

    /**
     * Get metadata for all registered providers
     *
     * @return array<string, array{name: string, displayName: string, refillAvailable: bool, description: string, instructions: string}>
     */
    public function getAllMetadata(): array
    {
        $metadata = [];

        foreach ($this->providers as $key => $provider) {
            $metadata[$key] = [
                'name' => $provider->getName(),
                'displayName' => $provider->getDisplayName(),
                'refillAvailable' => $provider->supportsRefill(),
                'description' => $provider->getDescription(),
                'instructions' => $provider->getInstructions(),
            ];
        }

        return $metadata;
    }
}
