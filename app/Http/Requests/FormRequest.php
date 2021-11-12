<?php

namespace App\Http\Requests;

use App\Components\FlashMessages;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest as IlluminationFormRequest;

class FormRequest extends IlluminationFormRequest
{
    use FlashMessages;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Load validator error messages to standard notifications array
     *
     * @return void
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            foreach ($validator->errors()->all() as $message) {
                self::addSimpleDangerMessage($message);
            }
        });
    }
}
