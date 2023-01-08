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
    public function rules()
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
        ];
    }
}
