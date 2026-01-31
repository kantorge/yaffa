<?php

namespace App\Http\Requests;

class StoreAiDocumentRequest extends FormRequest
{
    public function rules(): array
    {
        $maxFilesPerSubmission = config('ai-documents.file_upload.max_files_per_submission', 10);
        $maxFileSize = config('ai-documents.file_upload.max_file_size_mb', 50);
        $allowedTypes = config('ai-documents.file_upload.allowed_types', ['pdf', 'jpg', 'jpeg', 'png', 'txt']);

        return [
            'files' => [
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
                'nullable',
                'string',
                'max:10000',
            ],
            'custom_prompt' => [
                'nullable',
                'string',
                'max:5000',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'files.required_if' => 'You must provide either files or text input.',
            'files.max' => 'You can upload a maximum of ' . config('ai-documents.file_upload.max_files_per_submission', 10) . ' files.',
            'files.*.file' => 'Each file must be a valid file.',
            'files.*.max' => 'Each file must not exceed ' . config('ai-documents.file_upload.max_file_size_mb', 50) . 'MB.',
            'files.*.mimes' => 'Files must be of type: ' . implode(', ', config('ai-documents.file_upload.allowed_types', ['pdf', 'jpg', 'jpeg', 'png', 'txt'])),
        ];
    }

    protected function prepareForValidation(): void
    {
        // Ensure either files or text_input is provided
        if ((! $this->has('files') || empty($this->input('files'))) && ! $this->filled('text_input')) {
            $this->merge(['files' => null]);
        }
    }
}
