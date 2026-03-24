<?php

namespace Database\Factories;

use App\Models\AiUserSettings;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiUserSettings>
 */
class AiUserSettingsFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'ai_enabled' => false,
            'prompt_chat_history_enabled' => true,
            'ocr_language' => 'eng',
            'generic_document_language' => null,
            'image_max_width_vision' => null,
            'image_max_height_vision' => null,
            'image_quality_vision' => 85,
            'image_max_width_tesseract' => null,
            'image_max_height_tesseract' => null,
            'asset_similarity_threshold' => 0.5,
            'asset_max_suggestions' => 10,
            'match_auto_accept_threshold' => 0.95,
            'duplicate_date_window_days' => 3,
            'duplicate_amount_tolerance_percent' => 10.0,
            'duplicate_similarity_threshold' => 0.5,
            'category_matching_mode' => 'child_preferred',
        ];
    }

    public function enabled(): static
    {
        return $this->state(fn (): array => [
            'ai_enabled' => true,
        ]);
    }
}
