<?php

namespace App\Http\Requests;

class CloneFileImportProfileRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'min:2', 'max:191'],
        ];
    }
}
