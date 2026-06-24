<?php

namespace App\Http\Requests;

use App\Enums\ImportCanonicalField;
use App\Models\FileImportProfile;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\Rule;

class FileImportProfileUpdateRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['prohibited'],
            'key' => ['prohibited'],
            'type' => ['prohibited'],
            'file_type' => ['prohibited'],
            'name' => ['sometimes', 'string', 'min:2', 'max:191'],
            'delimiter' => ['sometimes', 'nullable', 'string', 'max:5'],
            'has_header_row' => ['sometimes', 'boolean'],
            'date_format' => ['sometimes', 'nullable', 'string', 'max:64'],
            'decimal_separator' => ['sometimes', 'nullable', 'string', 'max:10'],
            'thousand_separator' => ['sometimes', 'nullable', 'string', 'max:10'],
            'sign_handling' => ['sometimes', 'nullable', Rule::in(['as_is', 'inverted'])],
            'mapping_json' => ['sometimes', 'array'],
            'mapping_json.*' => ['string', Rule::in(ImportCanonicalField::values())],
            'options_json' => ['sometimes', 'nullable', 'array'],
            'options_json.defaults' => ['prohibited'],
            'options_json.matching_rules' => ['prohibited'],
            'options_json.actions' => ['prohibited'],
            'options_json.transform_catalog' => ['prohibited'],
            'active' => ['sometimes', 'boolean'],
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
                $fileType = $profile instanceof FileImportProfile ? $profile->file_type : 'csv';

                if ($fileType !== 'csv') {
                    return;
                }

                $mapping = (array) $this->input('mapping_json', []);
                if (empty($mapping)) {
                    return;
                }

                $this->validateCsvMappingJson($validator, $mapping);
            },
        ];
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
