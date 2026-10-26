<?php

namespace App\Models;

use App\Http\Traits\ModelOwnedByUserTrait;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property string|null $trigger_text
 * @property string $hint
 * @property bool $active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read User $user
 * @method static \Database\Factories\AiHintFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiHint newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiHint newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiHint query()
 * @mixin \Eloquent
 */
#[Fillable('name', 'trigger_text', 'hint', 'active')]
class AiHint extends Model
{
    use HasFactory;
    use ModelOwnedByUserTrait;

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }

    #[Scope]
    protected function active(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
