<?php

namespace App\Services;

use App\Models\AiUserSettings;
use App\Models\Category;
use App\Models\User;

class AiUserSettingsResolver
{
    public const string DEFAULT_CATEGORY_MATCHING_MODE = 'child_preferred';

    private const bool DEFAULT_AI_ENABLED = false;

    private const string DEFAULT_OCR_LANGUAGE = 'eng';

    private const int DEFAULT_IMAGE_MAX_WIDTH_VISION = 2048;

    private const int DEFAULT_IMAGE_MAX_HEIGHT_VISION = 2048;

    private const int DEFAULT_IMAGE_QUALITY_VISION = 85;

    private const float DEFAULT_ASSET_SIMILARITY_THRESHOLD = 0.5;

    private const int DEFAULT_ASSET_MAX_SUGGESTIONS = 10;

    private const float DEFAULT_MATCH_AUTO_ACCEPT_THRESHOLD = 0.95;

    private const int DEFAULT_DUPLICATE_DATE_WINDOW_DAYS = 3;

    private const float DEFAULT_DUPLICATE_AMOUNT_TOLERANCE_PERCENT = 10.0;

    private const float DEFAULT_DUPLICATE_SIMILARITY_THRESHOLD = 0.5;

    public const array CATEGORY_MATCHING_MODES = [
        'best_match',
        'parent_only',
        'parent_preferred',
        'child_only',
        'child_preferred',
    ];

    private const array CHILD_ORIENTED_CATEGORY_MATCHING_MODES = [
        'child_only',
        'child_preferred',
    ];

    /**
     * @return array<string, mixed>
     */
    public function resolveForUser(User $user): array
    {
        $settings = $this->getOrCreateForUser($user);

        return $this->resolveFromSettings($user, $settings);
    }

    public function isEnabledForUser(User $user): bool
    {
        $settings = $this->getOrCreateForUser($user);

        return (bool) $this->resolveSettingValue($settings->ai_enabled, self::DEFAULT_AI_ENABLED);
    }

    /**
     * @return array<string, mixed>
     */
    public function resolveFromSettings(User $user, AiUserSettings $settings): array
    {
        $categoryMatchingMode = (string) $this->resolveSettingValue(
            $settings->category_matching_mode,
            self::DEFAULT_CATEGORY_MATCHING_MODE
        );

        if (! in_array($categoryMatchingMode, self::CATEGORY_MATCHING_MODES, true)) {
            $categoryMatchingMode = self::DEFAULT_CATEGORY_MATCHING_MODE;
        }

        return [
            'ai_enabled' => (bool) $this->resolveSettingValue($settings->ai_enabled, self::DEFAULT_AI_ENABLED),
            'ocr_language' => (string) $this->resolveSettingValue($settings->ocr_language, self::DEFAULT_OCR_LANGUAGE),
            'image_max_width_vision' => (int) $this->resolveSettingValue($settings->image_max_width_vision, self::DEFAULT_IMAGE_MAX_WIDTH_VISION),
            'image_max_height_vision' => (int) $this->resolveSettingValue($settings->image_max_height_vision, self::DEFAULT_IMAGE_MAX_HEIGHT_VISION),
            'image_quality_vision' => (int) $this->resolveSettingValue($settings->image_quality_vision, self::DEFAULT_IMAGE_QUALITY_VISION),
            'image_max_width_tesseract' => $this->resolveSettingValue($settings->image_max_width_tesseract, null),
            'image_max_height_tesseract' => $this->resolveSettingValue($settings->image_max_height_tesseract, null),
            'asset_similarity_threshold' => (float) $this->resolveSettingValue($settings->asset_similarity_threshold, self::DEFAULT_ASSET_SIMILARITY_THRESHOLD),
            'asset_max_suggestions' => (int) $this->resolveSettingValue($settings->asset_max_suggestions, self::DEFAULT_ASSET_MAX_SUGGESTIONS),
            'match_auto_accept_threshold' => (float) $this->resolveSettingValue($settings->match_auto_accept_threshold, self::DEFAULT_MATCH_AUTO_ACCEPT_THRESHOLD),
            'duplicate_date_window_days' => (int) $this->resolveSettingValue($settings->duplicate_date_window_days, self::DEFAULT_DUPLICATE_DATE_WINDOW_DAYS),
            'duplicate_amount_tolerance_percent' => (float) $this->resolveSettingValue($settings->duplicate_amount_tolerance_percent, self::DEFAULT_DUPLICATE_AMOUNT_TOLERANCE_PERCENT),
            'duplicate_similarity_threshold' => (float) $this->resolveSettingValue($settings->duplicate_similarity_threshold, self::DEFAULT_DUPLICATE_SIMILARITY_THRESHOLD),
            'category_matching_mode' => $categoryMatchingMode,
            'warnings' => $this->resolveCategoryWarnings($user, $categoryMatchingMode),
        ];
    }

    public function getOrCreateForUser(User $user): AiUserSettings
    {
        return $user->aiUserSettings()->firstOrCreate([], $this->defaultSettings());
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateForUser(User $user, array $attributes): AiUserSettings
    {
        $settings = $this->getOrCreateForUser($user);

        $settings->fill($attributes);
        $settings->save();

        /** @var AiUserSettings $freshSettings */
        $freshSettings = $settings->refresh();

        return $freshSettings;
    }

    /**
     * @return array<int, array{code: string, message: string}>
     */
    public function resolveCategoryWarnings(User $user, string $categoryMatchingMode): array
    {
        if (! in_array($categoryMatchingMode, self::CHILD_ORIENTED_CATEGORY_MATCHING_MODES, true)) {
            return [];
        }

        if ($this->hasActiveChildCategories($user)) {
            return [];
        }

        return [
            [
                'code' => 'NO_ACTIVE_CHILD_CATEGORIES',
                'message' => __('Child-oriented category matching is selected, but there are no active child categories. You can still continue, but matching may be less specific.'),
            ],
        ];
    }

    private function hasActiveChildCategories(User $user): bool
    {
        return Category::query()
            ->where('user_id', $user->id)
            ->where('active', true)
            ->whereNotNull('parent_id')
            ->exists();
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultSettings(): array
    {
        return [
            'ai_enabled' => (bool) $this->resolveSettingValue(null, self::DEFAULT_AI_ENABLED),
            'ocr_language' => (string) $this->resolveSettingValue(null, self::DEFAULT_OCR_LANGUAGE),
            'image_max_width_vision' => (int) $this->resolveSettingValue(null, self::DEFAULT_IMAGE_MAX_WIDTH_VISION),
            'image_max_height_vision' => (int) $this->resolveSettingValue(null, self::DEFAULT_IMAGE_MAX_HEIGHT_VISION),
            'image_quality_vision' => (int) $this->resolveSettingValue(null, self::DEFAULT_IMAGE_QUALITY_VISION),
            'image_max_width_tesseract' => $this->resolveSettingValue(null, null),
            'image_max_height_tesseract' => $this->resolveSettingValue(null, null),
            'asset_similarity_threshold' => (float) $this->resolveSettingValue(null, self::DEFAULT_ASSET_SIMILARITY_THRESHOLD),
            'asset_max_suggestions' => (int) $this->resolveSettingValue(null, self::DEFAULT_ASSET_MAX_SUGGESTIONS),
            'match_auto_accept_threshold' => (float) $this->resolveSettingValue(null, self::DEFAULT_MATCH_AUTO_ACCEPT_THRESHOLD),
            'duplicate_date_window_days' => (int) $this->resolveSettingValue(null, self::DEFAULT_DUPLICATE_DATE_WINDOW_DAYS),
            'duplicate_amount_tolerance_percent' => (float) $this->resolveSettingValue(null, self::DEFAULT_DUPLICATE_AMOUNT_TOLERANCE_PERCENT),
            'duplicate_similarity_threshold' => (float) $this->resolveSettingValue(null, self::DEFAULT_DUPLICATE_SIMILARITY_THRESHOLD),
            'category_matching_mode' => (string) $this->resolveSettingValue(null, self::DEFAULT_CATEGORY_MATCHING_MODE),
        ];
    }

    private function resolveSettingValue(mixed $databaseValue, mixed $hardcodedFallback): mixed
    {
        if (null !== $databaseValue) {
            return $databaseValue;
        }

        return $hardcodedFallback;
    }
}
