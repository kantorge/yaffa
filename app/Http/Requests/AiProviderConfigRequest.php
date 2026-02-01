<?php

namespace App\Http\Requests;

use App\Models\AiProviderConfig;
use Illuminate\Validation\Rule;

class AiProviderConfigRequest extends FormRequest
{
    public function rules(): array
    {
        $supportedProviders = array_keys(config('ai-documents.providers', []));

        return [
            'provider' => [
                'required',
                'string',
                'max:' . self::DEFAULT_STRING_MAX_LENGTH, // Not really needed but for consistency
                Rule::in($supportedProviders),
            ],
            'model' => [
                'required',
                'string',
                'max:' . self::DEFAULT_STRING_MAX_LENGTH, // Not really needed but for consistency
                function ($attribute, $value, $fail) {
                    $provider = $this->input('provider');
                    $models = config('ai-documents.providers.'.$provider.'.models', []);

                    if (!in_array($value, $models, true)) {
                        $fail(__('The :attribute is invalid.', ['attribute' => $attribute]));
                    }
                },
            ],
            'api_key' => [
                'required',
                'string',
                'min:10',
                'max:500',
            ],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
        // Enforce business rule: only one provider config per user
            // Skip this check if we're updating an existing config or running a test
            if (!$this->route('aiProviderConfig') && !$this->routeIs('api.ai.config.test')) {
                $existingConfig = AiProviderConfig::where('user_id', auth()->id())->exists();

                if ($existingConfig) {
                    $validator->errors()->add(
                        'provider',
                        __('You already have an AI provider configuration. Please update your existing configuration instead.')
                    );
                }
            }
        });

        parent::withValidator($validator);
    }
}
