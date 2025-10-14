<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use App\Http\Traits\ModelOwnedByUserTrait;
use Database\Factories\TagFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * App\Models\Tag
 *
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property bool $active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection|TransactionItem[] $transactionItems
 * @property-read int|null $transaction_items_count
 * @property-read User $user
 * @method static Builder|Tag active()
 * @method static TagFactory factory(...$parameters)
 * @method static Builder|Tag newModelQuery()
 * @method static Builder|Tag newQuery()
 * @method static Builder|Tag query()
 * @method static Builder|Tag whereActive($value)
 * @method static Builder|Tag whereCreatedAt($value)
 * @method static Builder|Tag whereId($value)
 * @method static Builder|Tag whereName($value)
 * @method static Builder|Tag whereUpdatedAt($value)
 * @method static Builder|Tag whereUserId($value)
 */
class Tag extends Model
{
    use HasFactory;
    use ModelOwnedByUserTrait;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'name',
        'active',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }

    public function transactionItems(): BelongsToMany
    {
        return $this->belongsToMany(
            TransactionItem::class,
            'transaction_items_tags',
            'tag_id',
            'transaction_item_id'
        );
    }

    // Define the relation to transactions through transaction items
    public function transactions()
    {
        return Transaction::whereHas('transactionItems', function ($query) {
            $query->whereHas('tags', function ($query) {
                $query->where('tags.id', $this->id);
            });
        });
    }

    public function getTransactionCountAttribute(): int
    {
        return $this->transactions()->count();
    }

    /**
     * Scope a query to only include active entities.
     *
     * @param Builder $query
     * @return Builder
     */
    #[Scope]
    protected function active(Builder $query): Builder
    {
        return $query->where('active', 1);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
