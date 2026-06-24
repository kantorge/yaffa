<?php

namespace App\Http\Requests;

use App\Models\FileImportProfile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use Closure;

class ImportParseRequest extends FormRequest
{
    private function effectiveMaxFileSizeMb(): int
    {
        $configured = (int) config('yaffa.import_max_file_size_mb', 2);

        return max(1, $configured);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $maxFileSizeMb = $this->effectiveMaxFileSizeMb();

        return [
            'source_type' => [
                'required',
                'string',
                Rule::in(['qif', 'csv']),
            ],
            'account_id' => [
                'required',
                'integer',
                'exists:account_entities,id',
            ],
            'file_import_profile_id' => [
                'nullable',
                'integer',
                'exists:file_import_profiles,id',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if ($value === null || $value === '') {
                        return;
                    }

                    $profile = FileImportProfile::query()->find((int) $value);
                    if (! $profile instanceof FileImportProfile) {
                        return;
                    }

                    $sourceType = (string) $this->input('source_type');
                    if ($sourceType !== '' && $profile->file_type !== $sourceType) {
                        $fail(__('The selected import profile does not match the chosen source type.'));
                    }
                },
            ],
            'file' => [
                'required',
                File::types(['qif', 'txt', 'csv'])->max($maxFileSizeMb . 'mb'),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'source_type.in' => __('Only QIF and CSV imports are supported.'),
            'file.max' => __('The import file exceeds the configured maximum size of :size MB.', [
                'size' => $this->effectiveMaxFileSizeMb(),
            ]),
        ];
    }
}
