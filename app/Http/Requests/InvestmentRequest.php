<?php

namespace App\Http\Requests;

use App\Models\Investment;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * @property Investment $investment
 */
class InvestmentRequest extends FormRequest
{
    public function rules(): array
    {
        // Get all available investment price providers from Investment modell and add them to the validation rules
        // Only array keys are used in the validation rules
        $investmentPriceProviders = array_keys(
            App::make(Investment::class)->getAllInvestmentPriceProviders()
        );

        return [
            'name' => [
                'required',
                'min:' . self::DEFAULT_STRING_MIN_LENGTH,
                'max:' . self::DEFAULT_STRING_MAX_LENGTH,
                Rule::unique('investments')->where(function ($query) {
                    return $query
                        ->where('user_id', $this->user()->id)
                        ->when($this->investment, fn ($query) => $query->where('id', '!=', $this->investment->id));
                }),
            ],
            'symbol' => [
                'required',
                'min:' . self::DEFAULT_STRING_MIN_LENGTH,
                'max:' . self::DEFAULT_STRING_MAX_LENGTH,
                Rule::unique('investments')->where(function ($query) {
                    return $query
                        ->where('user_id', $this->user()->id)
                        ->when($this->investment, fn ($query) => $query->where('id', '!=', $this->investment->id));
                }),
            ],
            'isin' => [
                'nullable',
                'min:12',
                'max:12',
                Rule::unique('investments')->where(function ($query) {
                    return $query
                        ->where('user_id', $this->user()->id)
                        ->when($this->investment, fn ($query) => $query->where('id', '!=', $this->investment->id));
                }),
            ],
            'comment' => [
                'nullable',
                'max:' . self::DEFAULT_STRING_MAX_LENGTH,
            ],
            'active' => [
                'boolean',
            ],
            'auto_update' => [
                'boolean',
            ],
            'investment_group_id' => [
                'required',
                Rule::exists('investment_groups', 'id')->where(fn ($query) => $query->where('user_id', Auth::user()->id)),
            ],
            'currency_id' => [
                'required',
                Rule::exists('currencies', 'id')->where(fn ($query) => $query->where('user_id', Auth::user()->id)),
            ],
            'investment_price_provider' => [
                'nullable',
                Rule::in($investmentPriceProviders),
            ],
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Check for checkboxes and dropdown empty values
        $this->merge([
            'active' => $this->active ?? 0,
            'auto_update' => $this->auto_update ?? 0,
            'investment_price_provider' => $this->investment_price_provider ?? null,
        ]);
    }
}
