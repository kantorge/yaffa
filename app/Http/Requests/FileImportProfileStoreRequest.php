<?php

namespace App\Http\Requests;

use App\Enums\ImportCanonicalField;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\Rule;

class FileImportProfileStoreRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:191'],
            'user_id' => ['prohibited'],
            'key' => ['prohibited'],
            'type' => ['prohibited'],
            'file_type' => ['nullable', Rule::in(['csv', 'qif'])],
            'delimiter' => ['nullable', 'string', 'max:1'],
            'has_header_row' => ['nullable', 'boolean'],
            'date_format' => ['nullable', 'string', 'max:64'],
            'decimal_separator' => ['nullable', 'string', 'max:10'],
            'thousand_separator' => ['nullable', 'string', 'max:10'],
            'sign_handling' => ['nullable', Rule::in(['as_is', 'inverted'])],
            'mapping_json' => ['nullable', 'array'],
            'mapping_json.*' => ['string', Rule::in(ImportCanonicalField::values())],
            'options_json' => ['nullable', 'array'],
            'options_json.parser_settings' => ['sometimes', 'array'],
            'options_json.parser_settings.trim_strings' => ['sometimes', 'boolean'],
            'options_json.parser_settings.skip_empty_rows' => ['sometimes', 'boolean'],
            'options_json.comment_separator' => ['sometimes', 'string', 'max:32'],
            'options_json.defaults' => ['prohibited'],
            'options_json.matching_rules' => ['prohibited'],
            'options_json.actions' => ['prohibited'],
            'options_json.transform_catalog' => ['prohibited'],
            'active' => ['nullable', 'boolean'],
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

                $fileType = (string) $this->input('file_type', 'csv');

                if ($fileType !== 'csv') {
                    return;
                }

                $mapping = (array) $this->input('mapping_json', []);
                if (empty($mapping)) {
                    return;
                }

                $this->validateCsvMappingJson($validator, $mapping);
            },
            function (Validator $validator): void {
                $options = $this->input('options_json');
                if (! is_array($options)) {
                    return;
                }

                $this->validateOptionsJsonKeys($validator, $options);
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
