<?php

namespace App\Models;

use Database\Factories\TransactionItemFactory;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * App\Models\TransactionItem
 *
 * @property int $id
 * @property int $transaction_id
 * @property int|null $category_id
 * @property string $amount
 * @property string|null $comment
 * @property-read Category|null $category
 * @property-read Collection|Tag[] $tags
 * @property-read int|null $tags_count
 * @property-read Transaction $transaction
 * @method static TransactionItemFactory factory(...$parameters)
 * @method static Builder|TransactionItem newModelQuery()
 * @method static Builder|TransactionItem newQuery()
 * @method static Builder|TransactionItem query()
 * @method static Builder|TransactionItem whereAmount($value)
 * @method static Builder|TransactionItem whereCategoryId($value)
 * @method static Builder|TransactionItem whereComment($value)
 * @method static Builder|TransactionItem whereId($value)
 * @method static Builder|TransactionItem whereTransactionId($value)
 * @mixin Eloquent
 */
class TransactionItem extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'transaction_items';

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'transaction_id',
        'category_id',
        'amount',
        'comment',
    ];

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(
            Tag::class,
            'transaction_items_tags',
            'transaction_item_id',
            'tag_id'
        );
    }
}
