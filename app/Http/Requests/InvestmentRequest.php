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
                Rule::unique('investments')->where(fn ($query) => $query
                    ->where('user_id', $this->user()->id)
                    ->when($this->investment, fn ($query) => $query->where('id', '!=', $this->investment->id))),
            ],
            'symbol' => [
                'required',
                'min:' . self::DEFAULT_STRING_MIN_LENGTH,
                'max:' . self::DEFAULT_STRING_MAX_LENGTH,
                Rule::unique('investments')->where(fn ($query) => $query
                    ->where('user_id', $this->user()->id)
                    ->when($this->investment, fn ($query) => $query->where('id', '!=', $this->investment->id))),
            ],
            'isin' => [
                'nullable',
                'min:12',
                'max:12',
                Rule::unique('investments')->where(fn ($query) => $query
                    ->where('user_id', $this->user()->id)
                    ->when($this->investment, fn ($query) => $query->where('id', '!=', $this->investment->id))),
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
                'required_if_accepted:auto_update',
                Rule::in($investmentPriceProviders),
            ],
            'price_factor' => [
                'nullable',
                'numeric',
                'min:0.0001',
                'max:10000',
            ],
            'interest_rate' => [
                'nullable',
                'numeric',
                'min:0',
                'max:100',
            ],
            'maturity_date' => [
                'nullable',
                'date',
                'after:today',
            ],
            'scrape_url' => [
                'nullable',
                Rule::RequiredIf(fn ()  => in_array($this->investment_price_provider, ['web_scraping', 'wisealpha'])),
                'url',
            ],
            'scrape_selector' => [
                'exclude_unless:investment_price_provider,web_scraping',
                Rule::RequiredIf(fn () => $this->investment_price_provider === 'web_scraping'),
                'string',
                'min:1', // It's not likely to qualify as a selector, but let's go with the minimum
                'max:' . self::DEFAULT_STRING_MAX_LENGTH,
            ],
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Convert interest rate from percentage to decimal
        $interestRate = $this->interest_rate;
        if ($interestRate !== null && $interestRate !== '') {
            $interestRate = floatval($interestRate) / 100;
        } else {
            $interestRate = null;
        }

        // Check for checkboxes and dropdown empty values
        $this->merge([
            'active' => $this->active ?? 0,
            'auto_update' => $this->auto_update ?? 0,
            'investment_price_provider' => $this->investment_price_provider ?? null,
            'interest_rate' => $interestRate,
        ]);
    }
}
