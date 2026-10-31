<?php

namespace App\Http\Requests;

class ReferenceDataRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'updated_since' => ['nullable', 'date'],
        ];
    }
}
