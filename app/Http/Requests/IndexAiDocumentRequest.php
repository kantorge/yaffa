<?php

namespace App\Http\Requests;

class IndexAiDocumentRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'updated_since' => ['nullable', 'date'],
        ];
    }
}
