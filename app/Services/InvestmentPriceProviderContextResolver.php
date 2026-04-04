<?php

namespace App\Services;

use App\Contracts\InvestmentPriceProvider;
use App\Exceptions\PriceProviderException;
use App\Models\Investment;
use App\Models\InvestmentProviderConfig;

class InvestmentPriceProviderContextResolver
{
    public function __construct(
        private InvestmentPriceProviderRegistry $providerRegistry,
        private InvestmentProviderRateLimitPolicyResolver $policyResolver
    ) {
    }

    /**
     * @return array{
     *   provider: InvestmentPriceProvider,
     *   provider_key: string,
     *   provider_metadata: array<string, mixed>,
     *   investment_settings: array<string, mixed>,
     *   user_provider_config: InvestmentProviderConfig|null,
     *   credentials: array<string, mixed>,
     *   rate_limit_policy: array<string, int|string|null>
     * }
     */
    public function resolve(Investment $investment): array
    {
        $providerKey = $investment->investment_price_provider;

        if (! is_string($providerKey) || $providerKey === '') {
            throw new PriceProviderException(
                'Investment has no price provider configured',
                'none',
                $investment->symbol
            );
        }

        if (! $this->providerRegistry->has($providerKey)) {
            throw new PriceProviderException(
                "Investment has unknown provider: {$providerKey}",
                $providerKey,
                $investment->symbol
            );
        }

        $provider = $this->providerRegistry->get($providerKey);
        $providerMetadata = $this->providerRegistry->getMetadata($providerKey);

        if (mb_trim((string) $investment->symbol) === '') {
            throw new PriceProviderException(
                'Missing required investment symbol',
                $providerKey,
                $investment->symbol
            );
        }

        /** @var InvestmentProviderConfig|null $providerConfig */
        $providerConfig = $this->resolveProviderConfig($investment, $providerKey);

        if ($providerConfig && ! $providerConfig->enabled) {
            throw new PriceProviderException(
                'Provider configuration is disabled for this user',
                $providerKey,
                $investment->symbol
            );
        }

        $investmentSettings = $this->resolveAndValidateInvestmentSettings($investment, $providerMetadata, $providerKey);
        $credentials = is_array($providerConfig?->credentials) ? $providerConfig->credentials : [];
        $this->validateRequiredCredentials($providerMetadata, $credentials, $providerKey, $investment);

        $rateLimitPolicy = $this->policyResolver->resolve($investment, $providerMetadata, $providerConfig);

        return [
            'provider' => $provider,
            'provider_key' => $providerKey,
            'provider_metadata' => $providerMetadata,
            'investment_settings' => $investmentSettings,
            'user_provider_config' => $providerConfig,
            'credentials' => $credentials,
            'rate_limit_policy' => $rateLimitPolicy,
        ];
    }

    /**
     * Resolves the user's provider configuration for this investment.
     * Uses eager-loaded relationship if available, queries otherwise.
     */
    private function resolveProviderConfig(Investment $investment, string $providerKey): ?InvestmentProviderConfig
    {
        $user = $investment->user;

        // Use eager-loaded relationship if available to prevent N+1 queries
        if ($user->relationLoaded('investmentProviderConfigs')) {
            return $user->investmentProviderConfigs
                ->firstWhere('provider_key', $providerKey);
        }

        // Fall back to querying if relationship not eager-loaded
        return $user->investmentProviderConfigs()
            ->where('provider_key', $providerKey)
            ->first();
    }

    /**
     * @param  array<string, mixed>  $providerMetadata
     * @return array<string, mixed>
     */
    private function resolveAndValidateInvestmentSettings(Investment $investment, array $providerMetadata, string $providerKey): array
    {
        $settings = is_array($investment->provider_settings) ? $investment->provider_settings : [];

        $schema = $providerMetadata['investmentSettingsSchema'] ?? [];
        $requiredFields = is_array($schema['required'] ?? null) ? $schema['required'] : [];

        foreach ($requiredFields as $requiredField) {
            if (! isset($settings[$requiredField]) || $settings[$requiredField] === '') {
                throw new PriceProviderException(
                    'Missing required investment provider setting: ' . $requiredField,
                    $providerKey,
                    $investment->symbol
                );
            }
        }

        $properties = is_array($schema['properties'] ?? null) ? $schema['properties'] : [];
        foreach ($properties as $field => $fieldSchema) {
            if (! array_key_exists($field, $settings) || ! is_array($fieldSchema)) {
                continue;
            }

            $value = $settings[$field];
            $type = $fieldSchema['type'] ?? null;

            if ($type === 'string' && ! is_string($value)) {
                throw new PriceProviderException(
                    'Invalid provider setting type for ' . $field,
                    $providerKey,
                    $investment->symbol
                );
            }

            if (($fieldSchema['format'] ?? null) === 'url' && is_string($value) && ! filter_var($value, FILTER_VALIDATE_URL)) {
                throw new PriceProviderException(
                    'Invalid URL format for provider setting ' . $field,
                    $providerKey,
                    $investment->symbol
                );
            }
        }

        return $settings;
    }

    /**
     * @param  array<string, mixed>  $providerMetadata
     * @param  array<string, mixed>  $credentials
     */
    private function validateRequiredCredentials(
        array $providerMetadata,
        array $credentials,
        string $providerKey,
        Investment $investment
    ): void {
        $schema = $providerMetadata['userSettingsSchema'] ?? [];
        $requiredFields = is_array($schema['required'] ?? null) ? $schema['required'] : [];

        foreach ($requiredFields as $requiredField) {
            $value = array_key_exists($requiredField, $credentials) ? $credentials[$requiredField] : null;

            if ($value === null || (is_string($value) && mb_trim($value) === '')) {
                throw new PriceProviderException(
                    'Missing required provider credentials: ' . $requiredField,
                    $providerKey,
                    $investment->symbol
                );
            }
        }
    }
}
