<?php

namespace App\Http\Requests;

class SuggestFileImportProfileRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $maxSizeKb = max(1, (int) config('yaffa.import_max_file_size_mb', 2)) * 1024;

        return [
            'file' => ['required', 'file', 'mimes:csv,txt', "max:{$maxSizeKb}"],
            'account_id' => ['nullable', 'integer', 'exists:account_entities,id'],
        ];
    }
}
