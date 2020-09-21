<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;


class InvestmentGroupRequest extends FormRequest
{
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
        $investmentGroup = $this->route('investmentgroup');

        return (new \App\InvestmentGroup)->rules($investmentGroup);
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
                add_notification($message, 'danger');
            }
        });
    }
}
