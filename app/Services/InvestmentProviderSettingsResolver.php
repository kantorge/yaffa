<?php

namespace App\Services;

use App\Models\Investment;
use Illuminate\Validation\Rule;

/**
 * Resolves, normalises, and validates provider-specific settings for investments.
 *
 * Each investment price provider can declare a JSON-Schema-like schema through
 * {@see \App\Contracts\InvestmentPriceProvider::getInvestmentSettingsSchema()}.
 * This service acts as the single point of truth for:
 *
 *  - Reading raw provider settings from an Investment model and stripping any
 *    keys that are not declared in the schema ({@see normalize}).
 *  - Building Laravel validation rule arrays from the schema so they can be
 *    embedded directly into a Form Request ({@see rules}).
 *
 * The schema format is a subset of JSON Schema:
 * <code>
 * [
 *   'type'       => 'object',
 *   'required'   => ['symbol'],          // field names that must be present
 *   'properties' => [
 *     'symbol' => [
 *       'type'      => 'string',          // string | integer | number | boolean
 *       'minLength' => 1,
 *       'maxLength' => 20,
 *       'pattern'   => '^[A-Z]+$',        // PCRE – delimiters added automatically
 *       'enum'      => ['NYSE', 'NASDAQ'],
 *       'format'    => 'url',             // triggers the Laravel url rule
 *       'min'       => 0,                 // numeric lower bound
 *       'max'       => 100,               // numeric upper bound
 *     ],
 *   ],
 * ]
 * </code>
 */
class InvestmentProviderSettingsResolver
{
    public function __construct(private InvestmentPriceProviderRegistry $providerRegistry)
    {
    }

    /**
     * Return the normalised provider settings for the given investment.
     *
     * Convenience wrapper around {@see normalize} that reads the provider key
     * and raw settings directly from the Investment model.
     *
     * @return array<string, mixed>|null Normalised settings keyed by field name,
     *                                   or null when the investment has no
     *                                   recognised price provider.
     */
    public function resolveForInvestment(Investment $investment): ?array
    {
        return $this->normalize(
            $investment->investment_price_provider,
            $investment->provider_settings,
        );
    }

    /**
     * Normalise raw provider settings against the provider's declared schema.
     *
     * Only fields that are explicitly listed in the schema's `properties` are
     * kept; any additional keys in `$providerSettings` are silently discarded.
     * Fields that are missing from `$providerSettings` are set to `null` so
     * the returned array always contains exactly the keys declared by the
     * schema, making downstream code safe to access them without isset() guards.
     *
     * Returns null when:
     *  - `$providerKey` is empty or not registered in the provider registry, or
     *  - the provider does not declare an `investmentSettingsSchema`.
     *
     * @param  mixed  $providerSettings Raw, untrusted settings value (typically
     *                                  from the database or a request payload).
     * @return array<string, mixed>|null Schema-keyed settings array, or null.
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
     * Build Laravel validation rules for the provider settings fields.
     *
     * The rules are derived from the provider's JSON-Schema-like
     * `investmentSettingsSchema`. Each property in the schema is translated to
     * an array of Laravel rule strings / objects and stored under the key
     * `"{$baseField}.{$fieldName}"`, which is the dot-notation path expected by
     * Laravel's validator for nested arrays.
     *
     * The base field itself (`$baseField`) always receives `['nullable', 'array']`
     * so the validator accepts a missing or null outer value without error.
     *
     * Schema-to-rule mapping:
     *  - `required[]`        → `required` (otherwise `nullable`)
     *  - `type: string`      → `string`, plus `min:`, `max:` from minLength/maxLength
     *  - `type: integer`     → `integer`, plus `min:`, `max:`
     *  - `type: number`      → `numeric`, plus `min:`, `max:`
     *  - `type: boolean`     → `boolean`
     *  - `format: url`       → `url`
     *  - `pattern`           → `regex:/{pattern}/` (delimiters added if absent)
     *  - `enum`              → `Rule::in([...])`
     *
     * @param  string  $baseField Dot-notation prefix used when nesting the rules
     *                            inside a larger Form Request (default: `provider_settings`).
     * @return array<string, array<int, mixed>> Associative array of field paths
     *                                          to their validation rule arrays.
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
                // Ensure that the pattern is wrapped with / delimiters
                $pattern = $fieldSchema['pattern'];
                if ($pattern[0] !== '/') {
                    $pattern = '/' . $pattern;
                }
                if (mb_substr($pattern, -1) !== '/') {
                    $pattern .= '/';
                }
                $fieldRules[] = 'regex:' . $pattern;
            }

            if (isset($fieldSchema['enum']) && is_array($fieldSchema['enum'])) {
                $fieldRules[] = Rule::in($fieldSchema['enum']);
            }

            $rules[$baseField . '.' . $field] = $fieldRules;
        }

        return $rules;
    }

    /**
     * Retrieve the raw `investmentSettingsSchema` for a provider.
     *
     * Validates that `$providerKey` is a non-empty string registered in the
     * provider registry before fetching the provider's metadata, returning
     * null for any invalid or unknown key.
     *
     * @return array<string, mixed>|null The schema array, or null when the
     *                                   provider key is invalid or the provider
     *                                   does not declare a settings schema.
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
