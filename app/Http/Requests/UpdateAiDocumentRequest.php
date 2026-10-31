<?php

namespace App\Http\Requests;

class UpdateAiDocumentRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'note' => [
                'nullable',
                'string',
                'max:500',
            ],
            'status' => [
                'nullable',
                'string',
                'in:ready_for_processing',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'note.max' => 'Notes must not exceed 500 characters.',
            'status.in' => 'Invalid status value.',
        ];
    }
}
