<?php

namespace App\Http\Requests;

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
            'delimiter' => ['nullable', 'string', 'max:5'],
            'has_header_row' => ['nullable', 'boolean'],
            'date_format' => ['nullable', 'string', 'max:64'],
            'decimal_separator' => ['nullable', 'string', 'max:10'],
            'thousand_separator' => ['nullable', 'string', 'max:10'],
            'sign_handling' => ['nullable', 'string', 'max:32'],
            'mapping_json' => ['nullable', 'array'],
            'options_json' => ['nullable', 'array'],
            'options_json.matching_rules' => ['prohibited'],
            'options_json.actions' => ['prohibited'],
            'options_json.transform_catalog' => ['prohibited'],
            'active' => ['nullable', 'boolean'],
        ];
    }
}
