<?php

namespace App\Services;

/**
 * Helper class for accessing investment price provider metadata
 * Provides a convenient static interface to the registry
 */
class InvestmentPriceProviderMetadata
{
    /**
     * Get metadata for a specific provider
     *
     * @return array{name: string, displayName: string, refillAvailable: bool, description: string, instructions: string}
     *
     * @throws \App\Exceptions\PriceProviderException
     */
    public static function get(string $key): array
    {
        $registry = app(InvestmentPriceProviderRegistry::class);

        return $registry->getMetadata($key);
    }

    /**
     * Get metadata for all registered providers
     *
     * @return array<string, array{name: string, displayName: string, refillAvailable: bool, description: string, instructions: string}>
     */
    public static function all(): array
    {
        $registry = app(InvestmentPriceProviderRegistry::class);

        return $registry->getAllMetadata();
    }

    /**
     * Get display name for a provider
     *
     * @throws \App\Exceptions\PriceProviderException
     */
    public static function displayName(string $key): string
    {
        return self::get($key)['displayName'];
    }

    /**
     * Check if a provider exists
     */
    public static function has(string $key): bool
    {
        $registry = app(InvestmentPriceProviderRegistry::class);

        return $registry->has($key);
    }
}
