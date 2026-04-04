<?php

namespace App\Services;

use App\Models\Investment;
use App\Models\InvestmentProviderConfig;

class InvestmentProviderRateLimitPolicyResolver
{
    /**
     * @param  array<string, mixed>  $providerMetadata
     * @return array<string, int|string|null>
     */
    public function resolve(
        Investment $investment,
        array $providerMetadata,
        ?InvestmentProviderConfig $providerConfig
    ): array {
        $providerKey = (string) ($providerMetadata['key'] ?? $investment->investment_price_provider ?? 'unknown');
        $policy = $providerMetadata['rateLimitPolicy'] ?? [];

        $resolved = [
            'perSecond' => $this->normalizeNullableInt($policy['perSecond'] ?? null),
            'perMinute' => $this->normalizeNullableInt($policy['perMinute'] ?? null),
            'perDay' => $this->normalizeNullableInt($policy['perDay'] ?? null),
            'reserve' => max(0, (int) ($policy['reserve'] ?? 0)),
            'providerKey' => $providerKey,
            'bucketKey' => 'investment-price-provider:' . $providerKey . ':user:' . $investment->user_id,
        ];

        $overrideable = (bool) ($policy['overrideable'] ?? false);
        $overrides = $providerConfig?->rate_limit_overrides;
        if ($overrideable && is_array($overrides)) {
            $resolved = $this->applyValidatedOverrides($resolved, $overrides);
        }

        return $resolved;
    }

    /**
     * @param  array<string, int|string|null>  $resolved
     * @param  array<string, mixed>  $overrides
     * @return array<string, int|string|null>
     */
    private function applyValidatedOverrides(array $resolved, array $overrides): array
    {
        foreach ($overrides as $key => $value) {
            if (! in_array($key, ['perSecond', 'perMinute', 'perDay', 'reserve'], true)) {
                continue;
            }

            if (! is_numeric($value)) {
                continue;
            }

            $numericValue = (int) $value;
            if ($numericValue < 1) {
                continue;
            }

            $resolved[$key] = $numericValue;
        }

        return $resolved;
    }

    private function normalizeNullableInt(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        return (int) $value;
    }
}
