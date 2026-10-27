<?php

namespace App\Http\Requests;

class IndexAiDocumentRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'updated_since' => ['nullable', 'date'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
        ];
    }
}
