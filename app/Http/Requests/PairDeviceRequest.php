<?php

namespace App\Http\Requests;

class PairDeviceRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:' . self::DEFAULT_STRING_MIN_LENGTH, 'max:' . self::DEFAULT_STRING_MAX_LENGTH],
        ];
    }
}
