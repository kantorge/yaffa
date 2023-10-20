<?php

namespace App\Http\Requests;

use App\Models\Investment;
use App\Rules\InvestmentConfigValueUnique;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class AccountEntityRequest extends FormRequest
{
    public function rules(): array
    {
        $rules = [
            'name' => [
                'required',
                'min:2',
                'max:191',
                Rule::unique('account_entities')->where(function ($query) {
                    return $query
                        ->where('user_id', $this->user()->id)
                        ->where('config_type', $this->config_type)
                        ->when($this->account_entity, function ($query) {
                            return $query
                                ->where('id', '!=', $this->account_entity->id);
                        });
                }),
            ],
            'config_type' => 'required|in:account,payee,investment',
            'active' => 'boolean',
            'alias' => [
                'nullable',
                'string',
            ],
        ];

        if ($this->config_type === 'account') {
            $rules = array_merge($rules, [
                'config.opening_balance' => [
                    'required',
                    'numeric',
                ],
                'config.account_group_id' => [
                    'required',
                    Rule::exists('account_groups', 'id')->where(fn ($query) => $query->where('user_id', Auth::user()->id)),
                ],
                'config.currency_id' => [
                    'required',
                    Rule::exists('currencies', 'id')->where(fn ($query) => $query->where('user_id', Auth::user()->id)),
                ],
            ]);
        }

        if ($this->config_type === 'payee') {
            $rules = array_merge($rules, [
                'config.category_id' => [
                    'nullable',
                    Rule::exists('categories', 'id')->where(fn ($query) => $query->where('user_id', Auth::user()->id)),
                ],
                'config.preferred' => [
                    'nullable',
                    'array',
                ],
                'config.preferred.*' => [
                    Rule::exists('categories', 'id')->where(fn ($query) => $query->where('user_id', Auth::user()->id)),
                    // TODO: prevent items to be added from other select
                    Rule::notIn('config.not_preferred'),
                ],
                'config.not_preferred' => [
                    'nullable',
                    'array',
                ],
                'config.not_preferred.*' => [
                    Rule::exists('categories', 'id')->where(fn ($query) => $query->where('user_id', Auth::user()->id)),
                    // TODO: prevent items to be added from other select
                    Rule::notIn('config.preferred'),
                    'different:config.category_id',
                ],
            ]);
        }

        if ($this->config_type === 'investment') {
            // Get all available investment price providers from Investment modell and add them to the validation rules
            // Only array keys are used in the validation rules
            $investmentPriceProviders = array_keys(
                App::make(Investment::class)->getAllInvestmentPriceProviders()
            );

            $rules = array_merge($rules, [
                'config.symbol' => [
                    'nullable',
                    'string',
                    'max:191',
                    new InvestmentConfigValueUnique('symbol', $this->account_entity),
                ],
                'config.isin' => [
                    'nullable',
                    'string',
                    'max:12',
                    new InvestmentConfigValueUnique('isin', $this->account_entity),
                ],
                'config.auto_update' => 'boolean',
                'investment_price_provider' => [
                    'nullable',
                    Rule::in($investmentPriceProviders),
                ],
                'config.investment_group_id' => [
                    'required',
                    Rule::exists('investment_groups', 'id')
                        ->where(fn ($query) => $query->where('user_id', Auth::user()->id)),
                ],
                'config.currency_id' => [
                    'required',
                    Rule::exists('currencies', 'id')
                        ->where(fn ($query) => $query->where('user_id', Auth::user()->id)),
                ],
            ]);
        }

        return $rules;
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Ensure that checkbox values are available
        $this->merge([
            'active' => $this->active ?? 0,
            'config.category_id' => $this->config->category_id ?? null,
            'config.auto_update' => $this->config->auto_update ?? 0,
            'config.investment_price_provider' => $this->config->investment_price_provider ?? null,
        ]);
    }
}
