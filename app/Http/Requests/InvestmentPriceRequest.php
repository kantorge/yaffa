<?php

namespace App\Http\Requests;

use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class InvestmentPriceRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     * Pass ID to unique check, if it exists in request
     */
    public function rules(): array
    {
        return [
            'date' => [
                'required',
                'date',
                Rule::unique('investment_prices')->where(fn ($query) => $query
                    ->where('investment_id', $this->investment_id))->ignore($this->id),
            ],
            'price' => [
                'required',
                'numeric',
                // Fit in signed DECIMAL(20,10) range
                'min:0.0000000001',
                'max:9999999999.9999999999',
            ],
            'investment_id' => [
                'required',
                Rule::exists('investments', 'id')->where(fn ($query) => $query->where('user_id', Auth::user()->id)),
            ],
        ];
    }
}
