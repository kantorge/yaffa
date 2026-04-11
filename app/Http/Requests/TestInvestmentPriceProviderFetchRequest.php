<?php

namespace App\Http\Requests;

use App\Services\InvestmentPriceProviderRegistry;
use App\Services\InvestmentProviderSettingsResolver;
use Illuminate\Validation\Rule;

class TestInvestmentPriceProviderFetchRequest extends FormRequest
{
    public function rules(
        InvestmentPriceProviderRegistry $providerRegistry,
        InvestmentProviderSettingsResolver $settingsResolver,
    ): array {
        $providerKey = (string) $this->route('providerKey');
        $providerKeys = array_keys($providerRegistry->getAllMetadata());

        return [
            'provider_key' => [
                'required',
                'string',
                Rule::in($providerKeys),
            ],
            'symbol' => ['required', 'string', 'min:' . self::DEFAULT_STRING_MIN_LENGTH, 'max:' . self::DEFAULT_STRING_MAX_LENGTH],
            ...$settingsResolver->rules($providerKey),
        ];
    }

    protected function prepareForValidation(): void
    {
        $providerKey = (string) $this->route('providerKey');
        $resolver = app(InvestmentProviderSettingsResolver::class);

        $providerSettings = $resolver->normalize(
            $providerKey,
            $this->input('provider_settings'),
        );

        $this->merge([
            'provider_key' => $providerKey,
            'symbol' => is_string($this->input('symbol')) ? mb_trim((string) $this->input('symbol')) : null,
            'provider_settings' => $providerSettings,
        ]);
    }
}
