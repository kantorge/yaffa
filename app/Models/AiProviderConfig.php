<?php

namespace App\Models;

use App\Http\Traits\ModelOwnedByUserTrait;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property string $provider
 * @property string $model
 * @property string $api_key
 * @property bool $vision_enabled
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read User $user
 * @method static \Database\Factories\AiProviderConfigFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiProviderConfig newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiProviderConfig newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiProviderConfig query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiProviderConfig whereApiKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiProviderConfig whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiProviderConfig whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiProviderConfig whereModel($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiProviderConfig whereProvider($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiProviderConfig whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiProviderConfig whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiProviderConfig whereVisionEnabled($value)
 * @mixin \Eloquent
 */
#[Hidden('api_key')]
#[Fillable('provider', 'model', 'api_key', 'vision_enabled')]
class AiProviderConfig extends Model
{
    use HasFactory;
    use ModelOwnedByUserTrait;

    protected function casts(): array
    {
        return [
            'api_key' => 'encrypted',
            'vision_enabled' => 'boolean',
        ];
    }

    /**
     * Whether the configured model is still listed in config/ai-documents.php.
     * Models flagged `supported => false` are still listed, so they keep working until removed.
     */
    public function isModelAvailable(): bool
    {
        $models = config('ai-documents.providers.' . $this->provider . '.models', []);

        return array_is_list($models)
            ? in_array($this->model, $models, true)
            : array_key_exists($this->model, $models);
    }

    public function unavailableModelMessage(): string
    {
        return "The AI model \"{$this->model}\" is no longer available. Please select a different model in your AI provider settings.";
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
