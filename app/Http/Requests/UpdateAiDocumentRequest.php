<?php

namespace App\Http\Requests;

class UpdateAiDocumentRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'custom_prompt' => [
                'nullable',
                'string',
                'max:5000',
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
            'custom_prompt.max' => 'Custom prompt must not exceed 5000 characters.',
            'status.in' => 'Invalid status value.',
        ];
    }
}
