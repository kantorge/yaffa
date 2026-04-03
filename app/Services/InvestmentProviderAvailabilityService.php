<?php

namespace App\Services;

use App\Models\InvestmentProviderConfig;
use App\Models\User;

class InvestmentProviderAvailabilityService
{
    public function __construct(private InvestmentPriceProviderRegistry $providerRegistry)
    {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function forUser(User $user, bool $includeUnavailable = false): array
    {
        $configs = $user->investmentProviderConfigs()
            ->orderBy('provider_key')
            ->get()
            ->keyBy('provider_key');

        $providers = [];
        foreach ($this->providerRegistry->getAllMetadata() as $providerKey => $metadata) {
            $config = $configs->get($providerKey);
            $status = $this->resolveStatus($providerKey, $metadata, $config);
            $provider = [
                ...$metadata,
                'available' => $status['available'],
                'statusLabel' => $status['statusLabel'],
                'statusDescription' => $status['statusDescription'],
                'reasonFlags' => $status['reasonFlags'],
                'currentConfig' => $this->serializeConfig($config),
            ];

            if ($includeUnavailable || $provider['available']) {
                $providers[] = $provider;
            }
        }

        return $providers;
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array{available: bool, statusLabel: string, statusDescription: string, reasonFlags: array<int, string>}
     */
    private function resolveStatus(
        string $providerKey,
        array $metadata,
        ?InvestmentProviderConfig $config,
    ): array {
        $requiredFields = $metadata['userSettingsSchema']['required'] ?? [];
        if (! is_array($requiredFields)) {
            $requiredFields = [];
        }

        $hasCredentials = false;
        if ($config !== null && is_array($config->credentials) && ! empty($config->credentials)) {
            // Validate that all required fields are present and non-empty
            $hasCredentials = true;
            foreach ($requiredFields as $field) {
                if (empty($config->credentials[$field] ?? null)) {
                    $hasCredentials = false;
                    break;
                }
            }
        }

        if ($config && ! $config->enabled) {
            return [
                'available' => false,
                'statusLabel' => __('Disabled'),
                'statusDescription' => __('This provider is configured but disabled in your account settings.'),
                'reasonFlags' => ['disabled'],
            ];
        }

        if ($requiredFields === []) {
            return [
                'available' => true,
                'statusLabel' => __('Ready'),
                'statusDescription' => __('No account-level credentials are required for this provider.'),
                'reasonFlags' => ['no_credentials_required'],
            ];
        }

        if ($hasCredentials) {
            return [
                'available' => true,
                'statusLabel' => __('Configured'),
                'statusDescription' => __('This provider is configured and can be used for automatic updates.'),
                'reasonFlags' => ['configured'],
            ];
        }

        if ($config) {
            return [
                'available' => false,
                'statusLabel' => __('Credentials missing'),
                'statusDescription' => __('The provider configuration exists but required credentials are missing.'),
                'reasonFlags' => ['missing_credentials'],
            ];
        }

        return [
            'available' => false,
            'statusLabel' => __('Setup required'),
            'statusDescription' => __('This provider needs user-level credentials before it can be scheduled.'),
            'reasonFlags' => ['setup_required'],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function serializeConfig(?InvestmentProviderConfig $config): ?array
    {
        if (! $config) {
            return null;
        }

        return [
            'id' => $config->id,
            'provider_key' => $config->provider_key,
            'options' => $config->options,
            'enabled' => $config->enabled,
            'last_error' => $config->last_error,
            'plan' => $config->plan,
            'rate_limit_overrides' => $config->rate_limit_overrides,
            'has_credentials' => ! empty($config->credentials),
            'created_at' => $config->created_at,
            'updated_at' => $config->updated_at,
        ];
    }
}
