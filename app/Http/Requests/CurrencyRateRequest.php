<?php

namespace App\Http\Requests;

use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class CurrencyRateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $rules = [
            'date' => [
                'required',
                'date',
            ],
            'rate' => [
                'required',
                'numeric',
                'min:0.0001',
                'max:9999999999.9999',
            ],
            'from_id' => [
                'required',
                Rule::exists('currencies', 'id')->where(fn ($query) => $query->where('user_id', Auth::user()->id)),
            ],
            'to_id' => [
                'required',
                Rule::exists('currencies', 'id')->where(fn ($query) => $query->where('user_id', Auth::user()->id)),
            ],
        ];

        // Add unique constraint, ignoring current record if updating
        if ($this->isMethod('PUT') || $this->isMethod('PATCH')) {
            $rules['date'][] = Rule::unique('currency_rates')
                ->where(fn ($query) => $query
                    ->where('from_id', $this->from_id)
                    ->where('to_id', $this->to_id))
                ->ignore($this->route('currency_rate'));
        } else {
            $rules['date'][] = Rule::unique('currency_rates')
                ->where(fn ($query) => $query
                    ->where('from_id', $this->from_id)
                    ->where('to_id', $this->to_id));
        }

        return $rules;
    }
}
