<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class UserRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'language' => [
                'required',
                Rule::in(array_keys(config('app.available_languages'))),
            ],
            'locale' => [
                'required',
                Rule::in(array_keys(config('app.available_locales'))),
            ],
            'start_date' => [
                'required',
                'date',
                'before:end_date',
                'before:today',
            ],
            'end_date' => [
                'required',
                'date',
                'after:start_date',
            ]
        ];
    }
}
