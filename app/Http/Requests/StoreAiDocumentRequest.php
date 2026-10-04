<?php

namespace App\Http\Requests;

use App\Services\UploadLimitService;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreAiDocumentRequest extends FormRequest
{
    public function rules(): array
    {
        $maxFilesPerSubmission = config('ai-documents.file_upload.max_files_per_submission');
        $maxFileSize = UploadLimitService::maxFileMb();
        $allowedTypes = config('ai-documents.file_upload.allowed_types');

        return [
            'files' => [
                'required_without:text_input',
                'prohibited_if:source,mobile_notification',
                'nullable',
                'array',
                'max:' . $maxFilesPerSubmission,
            ],
            'files.*' => [
                'nullable',
                'file',
                'max:' . ($maxFileSize * 1024),
                'mimes:' . implode(',', $allowedTypes),
            ],
            'text_input' => [
                'required_without:files',
                'nullable',
                'string',
                'max:10000',
            ],
            'custom_prompt' => [
                'nullable',
                'string',
                'max:5000',
            ],
            'captured_at' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:1000'],
            'source' => ['nullable', 'in:mobile_scan,mobile_share,mobile_notification'],
            // Text-only payment notification captured on the phone
            'source_app' => ['required_if:source,mobile_notification', 'nullable', 'string', 'max:191'],
            'title' => ['nullable', 'string', 'max:255'],
            'text' => ['required_if:source,mobile_notification', 'nullable', 'string', 'max:10000'],
            'posted_at' => ['nullable', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'files.required_without' => 'You must provide either files or text input.',
            'files.max' => 'You can upload a maximum of ' . config('ai-documents.file_upload.max_files_per_submission') . ' files.',
            'files.*.file' => 'Each file must be a valid file.',
            'files.*.max' => 'Each file must not exceed ' . UploadLimitService::maxFileMb() . 'MB.',
            'files.*.uploaded' => 'A file could not be uploaded. Each file must not exceed ' . UploadLimitService::maxFileMb() . 'MB.',
            'files.*.mimes' => 'Files must be of type: ' . implode(', ', config('ai-documents.file_upload.allowed_types')),
        ];
    }

    /**
     * Oversize files get a dedicated error code and the limit, so clients can react precisely.
     */
    protected function failedValidation(Validator $validator): void
    {
        foreach ($validator->failed() as $field => $rules) {
            if (str_starts_with($field, 'files.') && (isset($rules['Max']) || isset($rules['Uploaded']))) {
                throw new HttpResponseException(response()->json([
                    'message' => $validator->errors()->first(),
                    'error' => [
                        'code' => 'FILE_TOO_LARGE',
                        'message' => $validator->errors()->first($field),
                        'limit_mb' => UploadLimitService::maxFileMb(),
                    ],
                    'errors' => $validator->errors()->messages(),
                ], 422));
            }
        }

        parent::failedValidation($validator);
    }

    protected function prepareForValidation(): void
    {
        // Ensure either files or text_input is provided
        // A notification is text-only: its body travels as the regular text input
        if ($this->input('source') === 'mobile_notification' && $this->filled('text')) {
            $this->merge(['text_input' => $this->input('text')]);
        }

        // input() never contains uploads, so they must be checked separately or they get nulled out below
        if (! $this->hasFile('files') && ! $this->filled('text_input')) {
            $this->merge(['files' => null]);
        }
    }
}
