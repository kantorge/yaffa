<?php

namespace App\Http\Requests;

class CsvImportProfileUpdateRequest extends FormRequest
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
            'name' => ['sometimes', 'string', 'min:2', 'max:191'],
            'delimiter' => ['sometimes', 'nullable', 'string', 'max:5'],
            'has_header_row' => ['sometimes', 'boolean'],
            'date_format' => ['sometimes', 'nullable', 'string', 'max:64'],
            'decimal_separator' => ['sometimes', 'nullable', 'string', 'max:10'],
            'thousand_separator' => ['sometimes', 'nullable', 'string', 'max:10'],
            'sign_handling' => ['sometimes', 'nullable', 'string', 'max:32'],
            'mapping_json' => ['sometimes', 'array'],
            'options_json' => ['sometimes', 'nullable', 'array'],
            'options_json.matching_rules' => ['prohibited'],
            'options_json.actions' => ['prohibited'],
            'options_json.transform_catalog' => ['prohibited'],
            'active' => ['sometimes', 'boolean'],
        ];
    }
}
