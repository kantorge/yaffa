<?php

namespace App\Models;

use App\Http\Traits\ModelOwnedByUserTrait;
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
class AiProviderConfig extends Model
{
    use HasFactory;
    use ModelOwnedByUserTrait;

    protected $fillable = [
        'provider',
        'model',
        'api_key',
        'vision_enabled',
    ];

    protected function casts(): array
    {
        return [
            'api_key' => 'encrypted',
            'vision_enabled' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
