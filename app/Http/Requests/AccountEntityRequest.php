<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;

class AccountEntityRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $rules = [
            'name' => 'required|min:2|max:191|unique:account_entities,name,' . \Request::instance()->id,
            'config_type' => 'required|in:account,payee',
            'active' => 'boolean',
        ];

        if ($this->config_type === 'account') {
            $rules = array_merge($rules, [
                'config.opening_balance'  => 'required|numeric',
                'config.account_group_id' => "required|exists:account_groups,id",
                'config.currency_id' => "required|exists:currencies,id",
            ]);
        }

        if ($this->config_type === 'payee') {
            $rules = array_merge($rules, [
                'config.category_id' => "nullable|exists:categories,id",
            ]);
        }

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
            'config.category_id' => $this->config->category_id ?? null,
        ]);
    }
}
