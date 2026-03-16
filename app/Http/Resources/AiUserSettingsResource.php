<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AiUserSettingsResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'ai_enabled' => (bool) data_get($this->resource, 'ai_enabled', false),
            'prompt_chat_history_enabled' => (bool) data_get($this->resource, 'prompt_chat_history_enabled', true),
            'ocr_language' => data_get($this->resource, 'ocr_language'),
            'image_max_width_vision' => data_get($this->resource, 'image_max_width_vision'),
            'image_max_height_vision' => data_get($this->resource, 'image_max_height_vision'),
            'image_quality_vision' => data_get($this->resource, 'image_quality_vision'),
            'image_max_width_tesseract' => data_get($this->resource, 'image_max_width_tesseract'),
            'image_max_height_tesseract' => data_get($this->resource, 'image_max_height_tesseract'),
            'asset_similarity_threshold' => data_get($this->resource, 'asset_similarity_threshold'),
            'asset_max_suggestions' => data_get($this->resource, 'asset_max_suggestions'),
            'match_auto_accept_threshold' => data_get($this->resource, 'match_auto_accept_threshold'),
            'duplicate_date_window_days' => data_get($this->resource, 'duplicate_date_window_days'),
            'duplicate_amount_tolerance_percent' => data_get($this->resource, 'duplicate_amount_tolerance_percent'),
            'duplicate_similarity_threshold' => data_get($this->resource, 'duplicate_similarity_threshold'),
            'category_matching_mode' => data_get($this->resource, 'category_matching_mode'),
            'warnings' => data_get($this->resource, 'warnings', []),
        ];
    }
}
