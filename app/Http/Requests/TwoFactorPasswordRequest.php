<?php

namespace App\Http\Requests;

class TwoFactorPasswordRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'password' => [
                'required',
                'string',
                'current_password',
            ],
        ];
    }
}
