<?php

namespace App\Http\Requests;

use App\Models\AiProviderConfig;
use Illuminate\Validation\Rule;

class AiProviderConfigRequest extends FormRequest
{
    public function rules(): array
    {
        $supportedProviders = array_keys(config('ai-documents.providers', []));
        $isUpdate = $this->isMethod('patch') || $this->isMethod('put');
        $isTest = $this->routeIs('api.ai.config.test');

        // API key is required for creation and testing, but optional for updates
        $apiKeyRules = $isUpdate ? [
            'nullable',
            'string',
            function ($attribute, $value, $fail) {
                // Allow __existing__ placeholder for tests
                if ($value === '__existing__') {
                    return;
                }

                // If provided (not empty and not null), validate length
                if (null !== $value && $value !== '') {
                    if (mb_strlen($value) < 10 || mb_strlen($value) > 500) {
                        $fail(__('The :attribute must be between 10 and 500 characters.', ['attribute' => $attribute]));
                    }
                }
            },
        ] : [
            'required',
            'string',
            'min:10',
            'max:500',
        ];

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
                    $modelsConfig = config('ai-documents.providers.' . $provider . '.models', []);
                    $models = array_is_list($modelsConfig) ? $modelsConfig : array_keys($modelsConfig);

                    if (!in_array($value, $models, true)) {
                        $fail(__('The :attribute is invalid.', ['attribute' => $attribute]));
                    }
                },
            ],
            'vision_enabled' => [
                'sometimes',
                'boolean',
            ],
            'api_key' => $apiKeyRules,
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
    }
}
