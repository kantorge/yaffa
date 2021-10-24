<?php

namespace App\Http\Requests;

use App\Components\FlashMessages;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class InvestmentRequest extends FormRequest
{
    use FlashMessages;

    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'name' => 'required|min:2|max:191|unique:investments,name,'.\Request::instance()->id,
            'symbol' => 'required|min:2|max:191|unique:investments,symbol,'.\Request::instance()->id,
            'comment' => 'nullable|max:191',
            'active' => 'boolean',
            'auto_update' => 'boolean',
            'investment_group_id' => 'required|exists:account_groups,id',
            'currency_id' => 'required|exists:currencies,id',
            'investment_price_provider_id' => 'nullable|exists:investment_price_providers,id',
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

    /**
     * Prepare the data for validation.
     *
     * @return void
     */
    protected function prepareForValidation()
    {
        // Check for checkboxes and dropdown empty values
        $this->merge([
            'active' => $this->active ?? 0,
            'auto_update' => $this->auto_update ?? 0,
            'investment_price_provider_id' => $this->investment_price_provider_id ?? null,
        ]);
    }
}
