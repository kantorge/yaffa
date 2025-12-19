<?php

namespace App\Http\Requests;

use App\Components\FlashMessages;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest as IlluminationFormRequest;

class FormRequest extends IlluminationFormRequest
{
    use FlashMessages;

    protected const int DEFAULT_STRING_MIN_LENGTH = 2;
    protected const int DEFAULT_STRING_MAX_LENGTH = 191;

    /**
     * Determine if the user is authorized to make this request.
     * As there are no user roles at the moment, all logged in users are authorized.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Load validator error messages to standard notifications array
     */
    public function withValidator(Validator $validator): void
    {
        // Add flash messages for non-API requests (traditional form submissions)
        if (!$this->expectsJson()) {
            $validator->after(function (Validator $validator) {
                foreach ($validator->errors()->all() as $message) {
                    self::addSimpleErrorMessage($message);
                }
            });
        }
    }
}
