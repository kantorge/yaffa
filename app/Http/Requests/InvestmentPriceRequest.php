<?php

namespace App\Http\Requests;

use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class InvestmentPriceRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     * Pass ID to unique check, if it exists in request
     *
     * @return array
     */
    public function rules()
    {
        return [
            'date' => [
                'required',
                'date',
                Rule::unique('investment_prices')->where(function ($query) {
                    return $query
                        ->where('investment_id', $this->investment_id);
                }),
            ],
            'price' => [
                'required',
                'numeric',
            ],
            'investment_id' => [
                'required',
                Rule::exists('investments', 'id')->where(function ($query) {
                    return $query->where('user_id', Auth::user()->id);
                }),
            ],
        ];
    }
}
