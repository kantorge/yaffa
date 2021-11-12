<?php

namespace App\Http\Requests;

use App\Http\Requests\FormRequest;
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
            'date' => 'required|date',
            'price' => [
                'required',
                'numeric',
                Rule::unique('investment_prices')->where(function ($query) {
                    return $query->when($this->investment_price, function ($query) {
                        return $query
                            ->where('date', $this->investment_price->date)
                            ->where('investment_id', $this->investment_price->investment_id);
                    });
                }),
            ],
            'investment_id' => 'required|exists:investments,id'
        ];
    }
}
