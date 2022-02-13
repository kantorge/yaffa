<?php

namespace App\Http\Requests;

use App\Http\Requests\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class InvestmentRequest extends FormRequest
{
    public function rules()
    {
        return [
            'name' => [
                'required',
                'min:2',
                'max:191',
                Rule::unique('investments')->where(function ($query) {
                    return $query
                        ->where('user_id', $this->user()->id)
                        ->when($this->investment, function ($query) {
                            return $query->where('id', '!=', $this->investment->id);
                        });
                }),
            ],
            'symbol' => [
                'required',
                'min:2',
                'max:191',
                Rule::unique('investments')->where(function ($query) {
                    return $query
                        ->where('user_id', $this->user()->id)
                        ->when($this->investment, function ($query) {
                            return $query->where('id', '!=', $this->investment->id);
                        });
                }),
            ],
            'isin' => [
                'nullable',
                'min:12',
                'max:12',
                Rule::unique('investments')->where(function ($query) {
                    return $query
                        ->where('user_id', $this->user()->id)
                        ->when($this->investment, function ($query) {
                            return $query->where('id', '!=', $this->investment->id);
                        });
                }),
            ],
            'comment' => [
                'nullable',
                'max:191',
            ],
            'active' => [
                'boolean',
            ],
            'auto_update' => [
                'boolean',
            ],
            'investment_group_id' => [
                'required',
                Rule::exists('investment_groups', 'id')->where(function ($query) {
                    return $query->where('user_id', Auth::user()->id);
                }),
            ],
            'currency_id' => [
                'required',
                Rule::exists('currencies', 'id')->where(function ($query) {
                    return $query->where('user_id', Auth::user()->id);
                }),
            ],
            'investment_price_provider_id' => [
                'nullable',
                'exists:investment_price_providers,id',
            ],
        ];
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
