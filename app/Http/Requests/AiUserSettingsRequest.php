<?php

namespace App\Http\Requests;

use App\Services\AiUserSettingsResolver;
use Illuminate\Validation\Rule;

class AiUserSettingsRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'ai_enabled' => ['sometimes', 'boolean'],
            'ocr_language' => ['sometimes', 'string', 'min:2', 'max:64'],
            'image_max_width_vision' => ['sometimes', 'integer', 'min:1', 'max:65535'],
            'image_max_height_vision' => ['sometimes', 'integer', 'min:1', 'max:65535'],
            'image_quality_vision' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'image_max_width_tesseract' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:65535'],
            'image_max_height_tesseract' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:65535'],
            'asset_similarity_threshold' => ['sometimes', 'numeric', 'between:0,1'],
            'asset_max_suggestions' => ['sometimes', 'integer', 'min:1', 'max:255'],
            'match_auto_accept_threshold' => ['sometimes', 'numeric', 'between:0,1'],
            'duplicate_date_window_days' => ['sometimes', 'integer', 'min:1', 'max:255'],
            'duplicate_amount_tolerance_percent' => ['sometimes', 'numeric', 'between:0,100'],
            'duplicate_similarity_threshold' => ['sometimes', 'numeric', 'between:0,1'],
            'category_matching_mode' => ['sometimes', 'string', Rule::in(AiUserSettingsResolver::CATEGORY_MATCHING_MODES)],
        ];
    }
}
