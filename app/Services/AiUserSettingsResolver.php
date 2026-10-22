<?php

namespace App\Services;

use App\Models\AiUserSettings;
use App\Models\Category;
use App\Models\User;

class AiUserSettingsResolver
{
    public const string DEFAULT_CATEGORY_MATCHING_MODE = 'child_preferred';

    private const bool DEFAULT_AI_ENABLED = false;

    private const bool DEFAULT_PROMPT_CHAT_HISTORY_ENABLED = true;

    private const string DEFAULT_OCR_LANGUAGE = 'eng';

    private const int DEFAULT_IMAGE_QUALITY_VISION = 85;

    private const float DEFAULT_ASSET_SIMILARITY_THRESHOLD = 0.5;

    private const int DEFAULT_ASSET_MAX_SUGGESTIONS = 10;

    public const float DEFAULT_MATCH_AUTO_ACCEPT_THRESHOLD = 0.95;

    public const float DEFAULT_MATCH_MIN_MARGIN = 0.1;

    private const int DEFAULT_DUPLICATE_DATE_WINDOW_DAYS = 3;

    private const float DEFAULT_DUPLICATE_AMOUNT_TOLERANCE_PERCENT = 10.0;

    private const float DEFAULT_DUPLICATE_SIMILARITY_THRESHOLD = 0.5;

    private const int DEFAULT_SAME_EVENT_MINUTES = 10;

    private const bool DEFAULT_AUTO_RECORD_ENABLED = false;

    private const int DEFAULT_ITEMIZATION_TIMEOUT_DAYS = 14;

    private const float DEFAULT_AUTO_RECORD_WILSON_MIN = 0.8;

    private const int DEFAULT_AUTO_RECORD_MIN_HISTORY = 10;

    private const float DEFAULT_AUTO_RECORD_AMOUNT_TOLERANCE_PERCENT = 20.0;

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
     * The named value sets of the strictness groups on the settings page. The page shows the name whose values
     * the user's settings equal (nothing is stored); `balanced` is the application default of every value.
     * Settings that are no matter of strictness (suggestion count, time windows) are not part of a group.
     *
     * @return array<string, array<string, array<string, int|float>>>
     */
    public static function presets(): array
    {
        return [
            'asset_matching' => [
                'loose' => ['asset_similarity_threshold' => 0.4, 'match_auto_accept_threshold' => 0.9, 'match_min_margin' => 0.05],
                'balanced' => ['asset_similarity_threshold' => self::DEFAULT_ASSET_SIMILARITY_THRESHOLD, 'match_auto_accept_threshold' => self::DEFAULT_MATCH_AUTO_ACCEPT_THRESHOLD, 'match_min_margin' => self::DEFAULT_MATCH_MIN_MARGIN],
                'strict' => ['asset_similarity_threshold' => 0.7, 'match_auto_accept_threshold' => 0.98, 'match_min_margin' => 0.15],
            ],
            // Strict flags more documents as possible duplicates
            'duplicate_detection' => [
                'loose' => ['duplicate_date_window_days' => 2, 'duplicate_amount_tolerance_percent' => 5.0, 'duplicate_similarity_threshold' => 0.7],
                'balanced' => ['duplicate_date_window_days' => self::DEFAULT_DUPLICATE_DATE_WINDOW_DAYS, 'duplicate_amount_tolerance_percent' => self::DEFAULT_DUPLICATE_AMOUNT_TOLERANCE_PERCENT, 'duplicate_similarity_threshold' => self::DEFAULT_DUPLICATE_SIMILARITY_THRESHOLD],
                'strict' => ['duplicate_date_window_days' => 7, 'duplicate_amount_tolerance_percent' => 20.0, 'duplicate_similarity_threshold' => 0.4],
            ],
            // Strict records fewer documents without a review
            'auto_record' => [
                'loose' => ['auto_record_min_history' => 5, 'auto_record_wilson_min' => 0.7, 'auto_record_amount_tolerance_percent' => 30.0],
                'balanced' => ['auto_record_min_history' => self::DEFAULT_AUTO_RECORD_MIN_HISTORY, 'auto_record_wilson_min' => self::DEFAULT_AUTO_RECORD_WILSON_MIN, 'auto_record_amount_tolerance_percent' => self::DEFAULT_AUTO_RECORD_AMOUNT_TOLERANCE_PERCENT],
                'strict' => ['auto_record_min_history' => 15, 'auto_record_wilson_min' => 0.9, 'auto_record_amount_tolerance_percent' => 10.0],
            ],
        ];
    }

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
            'prompt_chat_history_enabled' => (bool) $this->resolveSettingValue(
                $settings->prompt_chat_history_enabled,
                self::DEFAULT_PROMPT_CHAT_HISTORY_ENABLED
            ),
            'ocr_language' => (string) $this->resolveSettingValue($settings->ocr_language, self::DEFAULT_OCR_LANGUAGE),
            'generic_document_language' => $this->resolveSettingValue($settings->generic_document_language, null),
            'image_max_width_vision' => $this->resolveSettingValue($settings->image_max_width_vision, null),
            'image_max_height_vision' => $this->resolveSettingValue($settings->image_max_height_vision, null),
            'image_quality_vision' => (int) $this->resolveSettingValue($settings->image_quality_vision, self::DEFAULT_IMAGE_QUALITY_VISION),
            'image_max_width_tesseract' => $this->resolveSettingValue($settings->image_max_width_tesseract, null),
            'image_max_height_tesseract' => $this->resolveSettingValue($settings->image_max_height_tesseract, null),
            'asset_similarity_threshold' => (float) $this->resolveSettingValue($settings->asset_similarity_threshold, self::DEFAULT_ASSET_SIMILARITY_THRESHOLD),
            'asset_max_suggestions' => (int) $this->resolveSettingValue($settings->asset_max_suggestions, self::DEFAULT_ASSET_MAX_SUGGESTIONS),
            'match_auto_accept_threshold' => (float) $this->resolveSettingValue($settings->match_auto_accept_threshold, self::DEFAULT_MATCH_AUTO_ACCEPT_THRESHOLD),
            'match_min_margin' => (float) $this->resolveSettingValue($settings->match_min_margin, self::DEFAULT_MATCH_MIN_MARGIN),
            'duplicate_date_window_days' => (int) $this->resolveSettingValue($settings->duplicate_date_window_days, self::DEFAULT_DUPLICATE_DATE_WINDOW_DAYS),
            'duplicate_amount_tolerance_percent' => (float) $this->resolveSettingValue($settings->duplicate_amount_tolerance_percent, self::DEFAULT_DUPLICATE_AMOUNT_TOLERANCE_PERCENT),
            'duplicate_similarity_threshold' => (float) $this->resolveSettingValue($settings->duplicate_similarity_threshold, self::DEFAULT_DUPLICATE_SIMILARITY_THRESHOLD),
            'same_event_minutes' => (int) $this->resolveSettingValue($settings->same_event_minutes, self::DEFAULT_SAME_EVENT_MINUTES),
            'auto_record_enabled' => (bool) $this->resolveSettingValue($settings->auto_record_enabled, self::DEFAULT_AUTO_RECORD_ENABLED),
            'itemization_timeout_days' => (int) $this->resolveSettingValue($settings->itemization_timeout_days, self::DEFAULT_ITEMIZATION_TIMEOUT_DAYS),
            'auto_record_wilson_min' => (float) $this->resolveSettingValue($settings->auto_record_wilson_min, self::DEFAULT_AUTO_RECORD_WILSON_MIN),
            'auto_record_min_history' => (int) $this->resolveSettingValue($settings->auto_record_min_history, self::DEFAULT_AUTO_RECORD_MIN_HISTORY),
            'auto_record_amount_tolerance_percent' => (float) $this->resolveSettingValue($settings->auto_record_amount_tolerance_percent, self::DEFAULT_AUTO_RECORD_AMOUNT_TOLERANCE_PERCENT),
            'category_matching_mode' => $categoryMatchingMode,
            // null = finalized documents are kept forever
            'document_retention_days' => $this->resolveSettingValue($settings->document_retention_days, null),
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
            'prompt_chat_history_enabled' => (bool) $this->resolveSettingValue(null, self::DEFAULT_PROMPT_CHAT_HISTORY_ENABLED),
            'ocr_language' => (string) $this->resolveSettingValue(null, self::DEFAULT_OCR_LANGUAGE),
            'generic_document_language' => $this->resolveSettingValue(null, null),
            'image_max_width_vision' => $this->resolveSettingValue(null, null),
            'image_max_height_vision' => $this->resolveSettingValue(null, null),
            'image_quality_vision' => (int) $this->resolveSettingValue(null, self::DEFAULT_IMAGE_QUALITY_VISION),
            'image_max_width_tesseract' => $this->resolveSettingValue(null, null),
            'image_max_height_tesseract' => $this->resolveSettingValue(null, null),
            'asset_similarity_threshold' => (float) $this->resolveSettingValue(null, self::DEFAULT_ASSET_SIMILARITY_THRESHOLD),
            'asset_max_suggestions' => (int) $this->resolveSettingValue(null, self::DEFAULT_ASSET_MAX_SUGGESTIONS),
            'match_auto_accept_threshold' => (float) $this->resolveSettingValue(null, self::DEFAULT_MATCH_AUTO_ACCEPT_THRESHOLD),
            'match_min_margin' => (float) $this->resolveSettingValue(null, self::DEFAULT_MATCH_MIN_MARGIN),
            'duplicate_date_window_days' => (int) $this->resolveSettingValue(null, self::DEFAULT_DUPLICATE_DATE_WINDOW_DAYS),
            'duplicate_amount_tolerance_percent' => (float) $this->resolveSettingValue(null, self::DEFAULT_DUPLICATE_AMOUNT_TOLERANCE_PERCENT),
            'duplicate_similarity_threshold' => (float) $this->resolveSettingValue(null, self::DEFAULT_DUPLICATE_SIMILARITY_THRESHOLD),
            'same_event_minutes' => (int) $this->resolveSettingValue(null, self::DEFAULT_SAME_EVENT_MINUTES),
            'auto_record_enabled' => self::DEFAULT_AUTO_RECORD_ENABLED,
            'itemization_timeout_days' => self::DEFAULT_ITEMIZATION_TIMEOUT_DAYS,
            'auto_record_wilson_min' => self::DEFAULT_AUTO_RECORD_WILSON_MIN,
            'auto_record_min_history' => self::DEFAULT_AUTO_RECORD_MIN_HISTORY,
            'auto_record_amount_tolerance_percent' => self::DEFAULT_AUTO_RECORD_AMOUNT_TOLERANCE_PERCENT,
            'category_matching_mode' => (string) $this->resolveSettingValue(null, self::DEFAULT_CATEGORY_MATCHING_MODE),
            'document_retention_days' => $this->resolveSettingValue(null, null),
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
