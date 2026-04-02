<?php

namespace App\Http\Requests;

use App\Models\Investment;
use App\Services\InvestmentProviderSettingsResolver;
use Illuminate\Contracts\Validation\Validator;

/**
 * @property Investment $investment
 */
class UpdateInvestmentProviderSettingsRequest extends FormRequest
{
    public function rules(): array
    {
        $providerKey = $this->investment->investment_price_provider;

        return app(InvestmentProviderSettingsResolver::class)->rules($providerKey);
    }

    protected function prepareForValidation(): void
    {
        $providerKey = $this->investment->investment_price_provider;
        $resolver = app(InvestmentProviderSettingsResolver::class);

        $this->merge([
            'provider_settings' => $resolver->normalize(
                $providerKey,
                $this->input('provider_settings'),
            ),
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        parent::withValidator($validator);

        $validator->after(function (Validator $validator): void {
            if (! is_string($this->investment->investment_price_provider) || $this->investment->investment_price_provider === '') {
                $validator->errors()->add('investment_price_provider', __('This investment has no price provider configured.'));
            }
        });
    }
}
