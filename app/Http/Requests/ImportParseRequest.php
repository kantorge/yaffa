<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

class ImportParseRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $maxFileSizeMb = (int) config('yaffa.import_max_file_size_mb', 2);
        if ($maxFileSizeMb < 1) {
            $maxFileSizeMb = 1;
        }

        return [
            'source_type' => [
                'required',
                'string',
                Rule::in(['qif', 'csv']),
            ],
            'account_id' => [
                'required',
                'integer',
                'exists:account_entities,id',
            ],
            'file_import_profile_id' => [
                'nullable',
                'integer',
                'exists:file_import_profiles,id',
            ],
            'file' => [
                'required',
                File::types(['qif', 'txt', 'csv'])->max($maxFileSizeMb . 'mb'),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'source_type.in' => __('Only QIF and CSV imports are supported.'),
            'file.max' => __('The import file exceeds the configured maximum size of :size MB.', [
                'size' => (int) config('yaffa.import_max_file_size_mb', 2),
            ]),
        ];
    }
}
