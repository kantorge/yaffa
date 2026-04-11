<?php

namespace App\Models;

use App\Http\Traits\ModelOwnedByUserTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property string $item_description
 * @property int $category_id
 * @property int $usage_count
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Category $category
 * @property-read User $user
 * @method static \Database\Factories\CategoryLearningFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CategoryLearning newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CategoryLearning newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CategoryLearning query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CategoryLearning whereCategoryId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CategoryLearning whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CategoryLearning whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CategoryLearning whereItemDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CategoryLearning whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CategoryLearning whereUsageCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CategoryLearning whereUserId($value)
 * @mixin \Eloquent
 */
class CategoryLearning extends Model
{
    use HasFactory;
    use ModelOwnedByUserTrait;

    protected $table = 'category_learning';

    protected $fillable = [
        'item_description',
        'category_id',
        'usage_count',
    ];

    protected function casts(): array
    {
        return [
            'usage_count' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
