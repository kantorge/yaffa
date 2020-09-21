<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;

class InvestmentRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $rules = [
            'name' => 'required|min:2|max:191|unique:investments,name,' . \Request::instance()->id,
            'symbol' => 'required|min:2|max:191|unique:investments,symbol,' . \Request::instance()->id,
            'comment' => 'nullable|max:191',
            'active' => 'boolean',
            'investment_groups_id' => "required|exists:account_groups,id",
            'currencies_id' => "required|exists:currencies,id",
        ];

        return $rules;
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

    /**
     * Prepare the data for validation.
     *
     * @return void
     */
    protected function prepareForValidation()
    {
        //check for checkbox-es
        $this->merge([
            'active' => $this->active ?? 0,
        ]);
    }
}
