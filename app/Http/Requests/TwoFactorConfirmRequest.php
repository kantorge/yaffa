<?php

namespace App\Http\Requests;

class TwoFactorConfirmRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'code' => [
                'required',
                'string',
            ],
        ];
    }
}
