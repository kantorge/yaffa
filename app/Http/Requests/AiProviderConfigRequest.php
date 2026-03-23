<?php

namespace App\Http\Requests;

use App\Models\AiProviderConfig;
use Illuminate\Validation\Rule;

class AiProviderConfigRequest extends FormRequest
{
    public function rules(): array
    {
        $providerKeys = array_keys(config('ai-documents.providers', []));
        $isUpdate = $this->isMethod('patch') || $this->isMethod('put');

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
                Rule::in($providerKeys),
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
            $provider = (string) $this->input('provider');
            $model = (string) $this->input('model');

            if ($provider !== '' && ! $this->isProviderSupported($provider) && ! $this->isKeepingCurrentSelection($provider, $model)) {
                $validator->errors()->add(
                    'provider',
                    __('The selected provider is no longer supported for new configurations.')
                );
            }

            if ($provider !== '' && $model !== '' && ! $this->isModelSupported($provider, $model) && ! $this->isKeepingCurrentSelection($provider, $model)) {
                $validator->errors()->add(
                    'model',
                    __('The selected model is no longer supported for new configurations.')
                );
            }

            // Enforce business rule: only one provider config per user
            // Skip this check if we're updating an existing config or running a test
            if (!$this->route('aiProviderConfig') && !$this->routeIs('api.v1.ai.config.test')) {
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

    private function isProviderSupported(string $provider): bool
    {
        $providerConfig = config('ai-documents.providers.' . $provider, []);

        if (!is_array($providerConfig)) {
            return false;
        }

        return (bool) ($providerConfig['supported'] ?? true);
    }

    private function isModelSupported(string $provider, string $model): bool
    {
        $modelsConfig = config('ai-documents.providers.' . $provider . '.models', []);

        if (array_is_list($modelsConfig)) {
            return in_array($model, $modelsConfig, true);
        }

        if (!array_key_exists($model, $modelsConfig)) {
            return false;
        }

        $modelConfig = $modelsConfig[$model];
        if (!is_array($modelConfig)) {
            return true;
        }

        return (bool) ($modelConfig['supported'] ?? true);
    }

    private function isKeepingCurrentSelection(string $provider, string $model): bool
    {
        if ($provider === '' || $model === '') {
            return false;
        }

        if ($this->route('aiProviderConfig') instanceof AiProviderConfig) {
            $config = $this->route('aiProviderConfig');

            return $config->provider === $provider && $config->model === $model;
        }

        /** @var AiProviderConfig|null $existingConfig */
        $existingConfig = $this->user()?->aiProviderConfigs()->first();

        if ($existingConfig === null) {
            return false;
        }

        return $existingConfig->provider === $provider && $existingConfig->model === $model;
    }
}
