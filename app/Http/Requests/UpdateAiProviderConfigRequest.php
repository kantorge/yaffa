<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class UpdateAiProviderConfigRequest extends FormRequest
{
    public function rules(): array
    {
        $supportedProviders = array_keys(config('ai-documents.providers', []));

        return [
            'provider' => [
                'required',
                'string',
                Rule::in($supportedProviders),
            ],
            'model' => [
                'required',
                'string',
                'max:100',
            ],
            'api_key' => [
                'required',
                'string',
                'min:10',
                'max:500',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'provider.required' => 'Provider is required.',
            'provider.in' => 'Invalid provider selected.',
            'model.required' => 'Model is required.',
            'model.max' => 'Model name must not exceed 100 characters.',
            'api_key.required' => 'API key is required.',
            'api_key.min' => 'API key seems too short.',
            'api_key.max' => 'API key must not exceed 500 characters.',
        ];
    }
}
