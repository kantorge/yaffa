<?php

namespace App\Services;

use App\Models\Investment;
use Illuminate\Validation\Rule;

class InvestmentProviderSettingsResolver
{
    public function __construct(private InvestmentPriceProviderRegistry $providerRegistry)
    {
    }

    /**
     * @return array<string, mixed>|null
     */
    public function resolveForInvestment(Investment $investment): ?array
    {
        return $this->normalize(
            $investment->investment_price_provider,
            $investment->provider_settings,
        );
    }

    /**
     * @param  mixed  $providerSettings
     * @return array<string, mixed>|null
     */
    public function normalize(
        ?string $providerKey,
        mixed $providerSettings,
    ): ?array {
        $schema = $this->getSchema($providerKey);

        if ($schema === null) {
            return null;
        }

        $properties = is_array($schema['properties'] ?? null)
            ? $schema['properties']
            : [];
        $inputSettings = is_array($providerSettings) ? $providerSettings : [];
        $normalized = [];

        foreach (array_keys($properties) as $field) {
            $normalized[$field] = $inputSettings[$field] ?? null;
        }

        return $normalized;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(?string $providerKey, string $baseField = 'provider_settings'): array
    {
        $rules = [
            $baseField => ['nullable', 'array'],
        ];

        $schema = $this->getSchema($providerKey);

        if ($schema === null) {
            return $rules;
        }

        $properties = is_array($schema['properties'] ?? null)
            ? $schema['properties']
            : [];
        $requiredFields = is_array($schema['required'] ?? null)
            ? $schema['required']
            : [];

        foreach ($properties as $field => $fieldSchema) {
            if (! is_array($fieldSchema)) {
                continue;
            }

            $fieldRules = in_array($field, $requiredFields, true)
                ? ['required']
                : ['nullable'];

            $type = $fieldSchema['type'] ?? 'string';
            if ($type === 'string') {
                $fieldRules[] = 'string';
                if (isset($fieldSchema['minLength'])) {
                    $fieldRules[] = 'min:' . (int) $fieldSchema['minLength'];
                }
                if (isset($fieldSchema['maxLength'])) {
                    $fieldRules[] = 'max:' . (int) $fieldSchema['maxLength'];
                }
            }

            if ($type === 'integer') {
                $fieldRules[] = 'integer';
            }

            if ($type === 'number') {
                $fieldRules[] = 'numeric';
            }

            if ($type === 'boolean') {
                $fieldRules[] = 'boolean';
            }

            if (($fieldSchema['format'] ?? null) === 'url') {
                $fieldRules[] = 'url';
            }

            if (isset($fieldSchema['min']) && in_array($type, ['integer', 'number'], true)) {
                $fieldRules[] = 'min:' . $fieldSchema['min'];
            }

            if (isset($fieldSchema['max']) && in_array($type, ['integer', 'number'], true)) {
                $fieldRules[] = 'max:' . $fieldSchema['max'];
            }

            if (isset($fieldSchema['pattern']) && is_string($fieldSchema['pattern']) && $fieldSchema['pattern'] !== '') {
                $fieldRules[] = 'regex:' . $fieldSchema['pattern'];
            }

            if (isset($fieldSchema['enum']) && is_array($fieldSchema['enum'])) {
                $fieldRules[] = Rule::in($fieldSchema['enum']);
            }

            $rules[$baseField . '.' . $field] = $fieldRules;
        }

        return $rules;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function getSchema(?string $providerKey): ?array
    {
        if (! is_string($providerKey) || $providerKey === '' || ! $this->providerRegistry->has($providerKey)) {
            return null;
        }

        $metadata = $this->providerRegistry->getMetadata($providerKey);
        $schema = $metadata['investmentSettingsSchema'] ?? null;

        return is_array($schema) ? $schema : null;
    }
}
