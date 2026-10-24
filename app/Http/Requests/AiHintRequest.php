<?php

namespace App\Http\Requests;

class AiHintRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'trigger_text' => ['nullable', 'string', 'max:255'],
            'hint' => ['required', 'string', 'max:500'],
            'active' => ['required', 'boolean'],
        ];
    }
}
