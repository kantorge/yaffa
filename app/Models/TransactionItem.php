<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\TransactionItem
 *
 * @property int $id
 * @property int $transaction_id
 * @property int|null $category_id
 * @property string $amount
 * @property string|null $comment
 * @property-read \App\Models\Category|null $category
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Tag[] $tags
 * @property-read int|null $tags_count
 * @property-read \App\Models\Transaction $transaction
 * @method static \Database\Factories\TransactionItemFactory factory(...$parameters)
 * @method static \Illuminate\Database\Eloquent\Builder|TransactionItem newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|TransactionItem newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|TransactionItem query()
 * @method static \Illuminate\Database\Eloquent\Builder|TransactionItem whereAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TransactionItem whereCategoryId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TransactionItem whereComment($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TransactionItem whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TransactionItem whereTransactionId($value)
 * @mixin \Eloquent
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
     * @var array
     */
    protected $fillable = [
        'transaction_id',
        'category_id',
        'amount',
        'comment',
    ];

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function tags()
    {
        return $this->belongsToMany(
            Tag::class,
            'transaction_items_tags',
            'transaction_item_id',
            'tag_id'
        );
    }
}
