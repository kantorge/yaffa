<?php

namespace App\Http\Requests;

use App\Enums\ImportCanonicalField;
use App\Models\FileImportProfile;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\Rule;

class FileImportProfileRequest extends FormRequest
{
    private function isUpdating(): bool
    {
        return $this->route('profile') instanceof FileImportProfile;
    }

    /**
     * @param  array<int, mixed>  $rules
     * @return array<int, mixed>
     */
    private function sometimes(array $rules): array
    {
        if ($this->isUpdating()) {
            array_unshift($rules, 'sometimes');
        }

        return $rules;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['prohibited'],
            'key' => ['prohibited'],
            'type' => ['prohibited'],
            'file_type' => $this->isUpdating()
                ? ['prohibited']
                : ['nullable', Rule::in(['csv', 'qif'])],
            'name' => $this->isUpdating()
                ? ['sometimes', 'string', 'min:2', 'max:191']
                : ['required', 'string', 'min:2', 'max:191'],
            'delimiter' => $this->sometimes(['nullable', 'string', 'max:1']),
            'has_header_row' => $this->sometimes(['boolean']),
            'date_format' => $this->sometimes(['nullable', 'string', 'max:64']),
            'decimal_separator' => $this->sometimes(['nullable', 'string', 'max:10']),
            'thousand_separator' => $this->sometimes(['nullable', 'string', 'max:10']),
            'sign_handling' => $this->sometimes(['nullable', Rule::in(['as_is', 'inverted'])]),
            'mapping_json' => $this->sometimes(['array']),
            'mapping_json.*' => ['string', Rule::in(ImportCanonicalField::values())],
            'options_json' => $this->sometimes(['nullable', 'array']),
            'options_json.parser_settings' => ['sometimes', 'array'],
            'options_json.parser_settings.trim_strings' => ['sometimes', 'boolean'],
            'options_json.parser_settings.skip_empty_rows' => ['sometimes', 'boolean'],
            'options_json.comment_separator' => ['sometimes', 'string', 'max:32'],
            'options_json.defaults' => ['prohibited'],
            'options_json.matching_rules' => ['prohibited'],
            'options_json.actions' => ['prohibited'],
            'options_json.transform_catalog' => ['prohibited'],
            'active' => $this->sometimes(['boolean']),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'mapping_json.*.in' => 'Each column mapping must use a valid canonical field name: ' . implode(', ', ImportCanonicalField::values()) . '.',
            'sign_handling.in' => 'Sign handling must be "as_is" or "inverted".',
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                if (! $this->has('mapping_json')) {
                    return;
                }

                $profile = $this->route('profile');
                $fileType = $profile instanceof FileImportProfile
                    ? $profile->file_type
                    : (string) $this->input('file_type', 'csv');

                if ($fileType !== 'csv') {
                    return;
                }

                $this->validateCsvMappingJson($validator, (array) $this->input('mapping_json', []));
            },
            function (Validator $validator): void {
                $options = $this->input('options_json');
                if (! is_array($options)) {
                    return;
                }

                $this->validateOptionsJsonKeys($validator, $options);
            },
            function (Validator $validator): void {
                $profile = $this->route('profile');

                $decimalSep = $this->has('decimal_separator')
                    ? $this->input('decimal_separator')
                    : ($profile instanceof FileImportProfile ? $profile->decimal_separator : null);

                $thousandSep = $this->has('thousand_separator')
                    ? $this->input('thousand_separator')
                    : ($profile instanceof FileImportProfile ? $profile->thousand_separator : null);

                if (
                    is_string($decimalSep) && $decimalSep !== '' &&
                    is_string($thousandSep) && $thousandSep !== '' &&
                    $decimalSep === $thousandSep
                ) {
                    $validator->errors()->add(
                        'decimal_separator',
                        'The decimal separator and thousand separator must be different.',
                    );
                }
            },
        ];
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function validateOptionsJsonKeys(Validator $validator, array $options): void
    {
        $allowed = ['parser_settings', 'comment_separator'];

        foreach (array_keys($options) as $key) {
            if (! in_array($key, $allowed, true)) {
                $validator->errors()->add(
                    'options_json',
                    sprintf('The options_json key "%s" is not allowed. Allowed keys are: %s.', $key, implode(', ', $allowed)),
                );
            }
        }

        if (isset($options['parser_settings']) && is_array($options['parser_settings'])) {
            $allowedParserSettings = ['trim_strings', 'skip_empty_rows'];
            foreach (array_keys($options['parser_settings']) as $key) {
                if (! in_array($key, $allowedParserSettings, true)) {
                    $validator->errors()->add(
                        'options_json.parser_settings',
                        sprintf('The options_json.parser_settings key "%s" is not allowed. Allowed keys are: %s.', $key, implode(', ', $allowedParserSettings)),
                    );
                }
            }
        }
    }

    /**
     * @param  array<string, mixed>  $mapping
     */
    private function validateCsvMappingJson(Validator $validator, array $mapping): void
    {
        $mappedValues = array_values(array_filter(
            $mapping,
            fn (mixed $v) => is_string($v) && $v !== ImportCanonicalField::Ignore->value,
        ));

        if (! in_array(ImportCanonicalField::Date->value, $mappedValues, true)) {
            $validator->errors()->add('mapping_json', 'A column must be mapped to "date".');
        }

        if (! in_array(ImportCanonicalField::Amount->value, $mappedValues, true)) {
            $validator->errors()->add('mapping_json', 'A column must be mapped to "amount".');
        }

        $allowedMultiple = ImportCanonicalField::multiValueFields();
        $valueCounts = array_count_values($mappedValues);
        foreach ($valueCounts as $value => $count) {
            if ($count > 1 && ! in_array($value, $allowedMultiple, true)) {
                $validator->errors()->add(
                    'mapping_json',
                    sprintf('Only one column may be mapped to "%s". Multiple mappings are only allowed for "comment" and "reference".', $value),
                );
            }
        }
    }
}
