<?php

namespace App\Http\Requests;

use App\Http\Requests\FormRequest;
use App\Models\Investment;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\App;

class InvestmentRequest extends FormRequest
{
    public function rules()
    {
        // Get all available investment price providers from Investment modell and add them to the validation rules
        // Only array keys are used in the validation rules
        $investmentPriceProviders = array_keys(
                                        App::make(Investment::class)->getAllInvestmentPriceProviders()
                                    );

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
            'investment_price_provider' => [
                'nullable',
                Rule::in($investmentPriceProviders),
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
            'investment_price_provider' => $this->investment_price_provider ?? null,
        ]);
    }
}
