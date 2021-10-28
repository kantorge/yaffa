<?php

namespace App\Http\Requests;

use App\Components\FlashMessages;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class InvestmentPriceRequest extends FormRequest
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
        return [
            'date' => 'required|date',
            'price' => [
                'required',
                'numeric',
                /* TODO: validate for unique date / investment combinations
                https://stackoverflow.com/questions/50349775/laravel-unique-validation-on-multiple-columns
                https://www.itsolutionstuff.com/post/laravel-unique-validation-on-multiple-columns-exampleexample.html
                Rule::unique('servers')->where(function ($query) use($ip,$hostname) {
                    return $query->where('ip', $ip)
                    ->where('hostname', $hostname);
                }),
                */
            ],
            'investment_id' => 'required|exists:investments,id'
        ];
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
