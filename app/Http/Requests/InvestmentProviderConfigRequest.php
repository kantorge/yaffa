<?php

namespace App\Http\Requests;

use App\Models\InvestmentProviderConfig;
use App\Services\InvestmentPriceProviderRegistry;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\Rule;

class InvestmentProviderConfigRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $providerKey = (string) $this->route('providerKey');
        $registry = app(InvestmentPriceProviderRegistry::class);
        $providerMetadata = $registry->has($providerKey)
            ? $registry->getMetadata($providerKey)
            : null;

        $rules = [
            'enabled' => ['sometimes', 'boolean'],
            'credentials' => ['sometimes', 'array'],
            'options' => ['nullable', 'array'],
            'plan' => ['nullable', 'string', 'max:50'],
            'rate_limit_overrides' => ['nullable', 'array'],
        ];

        if (! $providerMetadata) {
            return $rules;
        }

        $schema = $providerMetadata['userSettingsSchema'] ?? [];
        $properties = $schema['properties'] ?? [];

        if (! is_array($properties)) {
            return $rules;
        }

        foreach ($properties as $field => $fieldSchema) {
            if (! is_array($fieldSchema)) {
                continue;
            }

            $fieldRules = ['sometimes'];
            $type = $fieldSchema['type'] ?? 'string';

            if ($type === 'string') {
                $fieldRules[] = 'string';
                $fieldRules[] = 'nullable';
                if (isset($fieldSchema['minLength'])) {
                    $fieldRules[] = 'min:' . (int) $fieldSchema['minLength'];
                }
                if (isset($fieldSchema['maxLength'])) {
                    $fieldRules[] = 'max:' . (int) $fieldSchema['maxLength'];
                }
            }

            if (($fieldSchema['format'] ?? null) === 'url') {
                $fieldRules[] = 'url';
            }

            if (isset($fieldSchema['enum']) && is_array($fieldSchema['enum'])) {
                $fieldRules[] = Rule::in($fieldSchema['enum']);
            }

            $rules['credentials.' . $field] = $fieldRules;
        }

        $rateLimitPolicy = $providerMetadata['rateLimitPolicy'] ?? [];
        $plans = $rateLimitPolicy['plans'] ?? [];
        if (is_array($plans) && $plans !== []) {
            $rules['plan'][] = Rule::in(array_keys($plans));
        }

        return $rules;
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        parent::withValidator($validator);

        $validator->after(function (Validator $validator): void {
            $providerKey = (string) $this->route('providerKey');
            $registry = app(InvestmentPriceProviderRegistry::class);

            if (! $registry->has($providerKey)) {
                $validator->errors()->add('provider_key', __('Unknown investment price provider.'));

                return;
            }

            $metadata = $registry->getMetadata($providerKey);
            $schema = $metadata['userSettingsSchema'] ?? [];
            $requiredFields = $schema['required'] ?? [];

            if (! is_array($requiredFields)) {
                $requiredFields = [];
            }

            $credentials = $this->input('credentials', []);
            if (! is_array($credentials)) {
                $credentials = [];
            }

            $existingConfig = $this->user()?->investmentProviderConfigs()
                ->where('provider_key', $providerKey)
                ->first();

            foreach ($requiredFields as $field) {
                $currentValue = $credentials[$field] ?? null;
                $existingValue = $existingConfig?->credentials[$field] ?? null;

                if (empty($currentValue) && empty($existingValue)) {
                    $validator->errors()->add('credentials.' . $field, __('This field is required.'));
                }
            }

            $this->validateRateLimitOverrides($validator, $metadata, $existingConfig);
        });
    }

    private function validateRateLimitOverrides(
        Validator $validator,
        array $providerMetadata,
        ?InvestmentProviderConfig $existingConfig
    ): void {
        $policy = $providerMetadata['rateLimitPolicy'] ?? [];
        $overrideable = (bool) ($policy['overrideable'] ?? false);

        $overridesInput = $this->input('rate_limit_overrides');
        if ($overridesInput === null) {
            return;
        }

        if (! $overrideable) {
            $validator->errors()->add('rate_limit_overrides', __('Rate limit overrides are not supported for this provider.'));

            return;
        }

        if (! is_array($overridesInput)) {
            return;
        }

        $bounds = $policy['overrideBounds'] ?? [];
        if (! is_array($bounds)) {
            $bounds = [];
        }

        $mergedOverrides = $existingConfig->rate_limit_overrides ?? [];
        $mergedOverrides = array_merge($mergedOverrides, $overridesInput);

        foreach ($overridesInput as $key => $value) {
            if (! is_numeric($value)) {
                $validator->errors()->add('rate_limit_overrides.' . $key, __('Override values must be numeric.'));

                continue;
            }

            if (! isset($bounds[$key]) || ! is_array($bounds[$key])) {
                $validator->errors()->add('rate_limit_overrides.' . $key, __('This override key is not allowed.'));

                continue;
            }

            $numericValue = (float) $value;
            $min = isset($bounds[$key]['min']) ? (float) $bounds[$key]['min'] : null;
            $max = isset($bounds[$key]['max']) ? (float) $bounds[$key]['max'] : null;

            if ($min !== null && $numericValue < $min) {
                $validator->errors()->add('rate_limit_overrides.' . $key, __('The value is below the allowed minimum.'));
            }

            if ($max !== null && $numericValue > $max) {
                $validator->errors()->add('rate_limit_overrides.' . $key, __('The value exceeds the allowed maximum.'));
            }
        }

        $effectivePerMinute = $mergedOverrides['perMinute'] ?? ($policy['perMinute'] ?? null);
        $effectivePerDay = $mergedOverrides['perDay'] ?? ($policy['perDay'] ?? null);

        if (
            is_numeric($effectivePerMinute)
            && is_numeric($effectivePerDay)
            && ((float) $effectivePerMinute * 1440) < (float) $effectivePerDay
        ) {
            $validator->errors()->add('rate_limit_overrides', __('Daily limit is too high for the configured per-minute limit.'));
        }
    }
}
