<?php

namespace App\Http\Requests;

use App\Components\FlashMessages;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class CurrencyRequest extends FormRequest
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
     * Get the validation rules that apply to the request.
     * Pass ID to unique check, if it exists in request
     *
     * @return array
     */
    public function rules()
    {
        $currency = $this->route('currency');

        return (new \App\Models\Currency())->rules($currency);
    }

    /**
     * Prepare the data for validation.
     *
     * @return void
     */
    protected function prepareForValidation()
    {
        //check for checkbox-es
        $this->merge([
            'base' => $this->base ?? null,
            'auto_update' => $this->auto_update ?? 0,
        ]);

        //make ISO code uppercase
        $this->merge([
            'iso_code' => Str::upper($this->iso_code),
        ]);
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
