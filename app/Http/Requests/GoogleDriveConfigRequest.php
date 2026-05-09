<?php

namespace App\Http\Requests;

use App\Models\GoogleDriveConfig;

class GoogleDriveConfigRequest extends FormRequest
{
    /**
     * Required Google service account JSON keys
     */
    private const REQUIRED_SERVICE_ACCOUNT_KEYS = [
        'type',
        'project_id',
        'private_key_id',
        'private_key',
        'client_email',
        'client_id',
        'auth_uri',
        'token_uri',
    ];

    public function rules(): array
    {
        $isUpdate = $this->isMethod('patch') || $this->isMethod('put');
        $isTest = $this->routeIs('api.v1.google-drive.config.test');

        // Service account JSON validation rules depend on context
        // For test: required (can be __existing__ or actual JSON)
        // For update: nullable (can be omitted, empty, __existing__, or new JSON)
        // For create: required with length validation
        if ($isTest) {
            $serviceAccountJsonRules = [
                'required',
                'string',
                function ($attribute, $value, $fail) {
                    // Allow __existing__ placeholder for tests
                    if ($value === '__existing__') {
                        return;
                    }

                    // Otherwise validate JSON format and required keys
                    $this->validateServiceAccountJson($attribute, $value, $fail);
                },
            ];
        } elseif ($isUpdate) {
            $serviceAccountJsonRules = [
                'nullable',
                'string',
                function ($attribute, $value, $fail) {
                    // Allow __existing__ placeholder
                    if ($value === '__existing__') {
                        return;
                    }

                    // If provided, validate JSON format and required keys
                    if (null !== $value && $value !== '') {
                        $this->validateServiceAccountJson($attribute, $value, $fail);
                    }
                },
            ];
        } else {
            // Create: required with length validation
            $serviceAccountJsonRules = [
                'required',
                'string',
                'min:100',
                'max:5000',
                function ($attribute, $value, $fail) {
                    $this->validateServiceAccountJson($attribute, $value, $fail);
                },
            ];
        }

        // Folder ID is required for create/test, nullable for update
        $folderIdRules = $isUpdate ? [
            'nullable',
            'string',
            'max:' . self::DEFAULT_STRING_MAX_LENGTH,
        ] : [
            'required',
            'string',
            'max:' . self::DEFAULT_STRING_MAX_LENGTH,
        ];

        return [
            'service_account_json' => $serviceAccountJsonRules,
            'folder_id' => $folderIdRules,
            'folder_name' => ['nullable', 'string', 'max:255'],
            'post_import_actions' => ['nullable', 'array'],
            'post_import_actions.*' => ['string', 'in:delete,trash,move_to_processed,rename_processed'],
            'processed_folder_id' => [
                'nullable',
                'string',
                'max:' . self::DEFAULT_STRING_MAX_LENGTH,
                'required_if_accepted:post_import_actions',
                function ($attribute, $value, $fail) {
                    $actions = $this->input('post_import_actions', []);
                    if (is_array($actions) && in_array('move_to_processed', $actions, true) && empty($value)) {
                        $fail(__('The processed folder ID is required when "Move to Processed" is selected.'));
                    }
                    if (! empty($value) && $value === $this->input('folder_id')) {
                        $fail(__('The processed folder must be different from the import folder.'));
                    }
                },
            ],
            'processed_folder_name' => ['nullable', 'string', 'max:255'],
            'enabled' => ['boolean'],
            'sync_interval_minutes' => ['sometimes', 'integer', 'min:1', 'max:1440'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Enforce business rule: only one Google Drive config per user (MVP)
            // Skip this check if we're updating an existing config or running a test
            if (!$this->route('googleDriveConfig') && !$this->routeIs('api.v1.google-drive.config.test')) {
                $existingConfig = GoogleDriveConfig::where('user_id', auth()->id())->exists();

                if ($existingConfig) {
                    $validator->errors()->add(
                        'folder_id',
                        __('You already have a Google Drive configuration. Please update your existing configuration instead.')
                    );
                }
            }
        });
    }

    /**
     * Validate service account JSON format and required keys
     */
    private function validateServiceAccountJson(string $attribute, string $value, callable $fail): void
    {
        // Validate JSON format
        $decoded = json_decode($value, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $fail(__('The :attribute must be valid JSON.', ['attribute' => $attribute]));
            return;
        }

        // Validate required Google service account keys
        foreach (self::REQUIRED_SERVICE_ACCOUNT_KEYS as $key) {
            if (!isset($decoded[$key]) || empty($decoded[$key])) {
                $fail(__('The :attribute is missing required key: :key', [
                    'attribute' => $attribute,
                    'key' => $key,
                ]));
                return;
            }
        }

        // Validate type is "service_account"
        if ($decoded['type'] !== 'service_account') {
            $fail(__('The :attribute must be a service account JSON key file.', ['attribute' => $attribute]));
        }
    }
}
