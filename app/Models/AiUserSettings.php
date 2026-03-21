<?php

namespace App\Models;

use App\Http\Traits\ModelOwnedByUserTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property bool $ai_enabled
 * @property bool $prompt_chat_history_enabled
 * @property string $ocr_language
 * @property int|null $image_max_width_vision
 * @property int|null $image_max_height_vision
 * @property int $image_quality_vision
 * @property int|null $image_max_width_tesseract
 * @property int|null $image_max_height_tesseract
 * @property float $asset_similarity_threshold
 * @property int $asset_max_suggestions
 * @property float $match_auto_accept_threshold
 * @property int $duplicate_date_window_days
 * @property float $duplicate_amount_tolerance_percent
 * @property float $duplicate_similarity_threshold
 * @property string $category_matching_mode
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read User $user
 * @method static \Database\Factories\AiUserSettingsFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiUserSettings newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiUserSettings newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiUserSettings query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiUserSettings whereAiEnabled($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiUserSettings whereAssetMaxSuggestions($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiUserSettings whereAssetSimilarityThreshold($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiUserSettings whereCategoryMatchingMode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiUserSettings whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiUserSettings whereDuplicateAmountTolerancePercent($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiUserSettings whereDuplicateDateWindowDays($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiUserSettings whereDuplicateSimilarityThreshold($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiUserSettings whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiUserSettings whereImageMaxHeightTesseract($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiUserSettings whereImageMaxHeightVision($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiUserSettings whereImageMaxWidthTesseract($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiUserSettings whereImageMaxWidthVision($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiUserSettings whereImageQualityVision($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiUserSettings whereMatchAutoAcceptThreshold($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiUserSettings whereOcrLanguage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiUserSettings wherePromptChatHistoryEnabled($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiUserSettings whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiUserSettings whereUserId($value)
 * @mixin \Eloquent
 */
class AiUserSettings extends Model
{
    /** @use HasFactory<\Database\Factories\AiUserSettingsFactory> */
    use HasFactory;

    use ModelOwnedByUserTrait;

    protected $fillable = [
        'ai_enabled',
        'prompt_chat_history_enabled',
        'ocr_language',
        'image_max_width_vision',
        'image_max_height_vision',
        'image_quality_vision',
        'image_max_width_tesseract',
        'image_max_height_tesseract',
        'asset_similarity_threshold',
        'asset_max_suggestions',
        'match_auto_accept_threshold',
        'duplicate_date_window_days',
        'duplicate_amount_tolerance_percent',
        'duplicate_similarity_threshold',
        'category_matching_mode',
    ];

    protected function casts(): array
    {
        return [
            'ai_enabled' => 'boolean',
            'prompt_chat_history_enabled' => 'boolean',
            'image_max_width_vision' => 'integer',
            'image_max_height_vision' => 'integer',
            'image_quality_vision' => 'integer',
            'image_max_width_tesseract' => 'integer',
            'image_max_height_tesseract' => 'integer',
            'asset_similarity_threshold' => 'float',
            'asset_max_suggestions' => 'integer',
            'match_auto_accept_threshold' => 'float',
            'duplicate_date_window_days' => 'integer',
            'duplicate_amount_tolerance_percent' => 'float',
            'duplicate_similarity_threshold' => 'float',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
