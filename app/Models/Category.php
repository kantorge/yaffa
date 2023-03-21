<?php

namespace App\Models;

use App\Http\Traits\ModelOwnedByUserTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * App\Models\Category
 *
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property bool $active
 * @property int|null $parent_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read mixed $full_name
 * @property-read Category|null $parent
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\AccountEntity[] $payeesNotPreferring
 * @property-read int|null $payees_not_preferring_count
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Transaction[] $transaction
 * @property-read int|null $transaction_count
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\TransactionItem[] $transactionItem
 * @property-read int|null $transaction_item_count
 * @property-read \App\Models\User $user
 * @method static Builder|Category active()
 * @method static \Database\Factories\CategoryFactory factory(...$parameters)
 * @method static Builder|Category newModelQuery()
 * @method static Builder|Category newQuery()
 * @method static Builder|Category query()
 * @method static Builder|Category topLevel()
 * @method static Builder|Category whereActive($value)
 * @method static Builder|Category whereCreatedAt($value)
 * @method static Builder|Category whereId($value)
 * @method static Builder|Category whereName($value)
 * @method static Builder|Category whereParentId($value)
 * @method static Builder|Category whereUpdatedAt($value)
 * @method static Builder|Category whereUserId($value)
 * @mixin \Eloquent
 */
class Category extends Model
{
    use HasFactory;
    use ModelOwnedByUserTrait;

    protected $table = 'categories';

    protected $with = [
        'parent',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'active',
        'parent_id',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    protected $appends = [
        'full_name',
    ];

    public static function rules()
    {
        return [
            'name' => 'required|min:2|max:191',
            'active' => 'boolean',
            'parent_id' => 'in:category,id',
        ];
    }

    public function parent()
    {
        return $this->belongsTo(self::class);
    }

    public function getFullNameAttribute(): string
    {
        return (isset($this->parent->name) ? $this->parent->name . ' > ' : '') . $this['name'];
    }

    /**
     * Scope a query to only include active entities.
     *
     * @param  Builder  $query
     * @return Builder
     */
    public function scopeActive($query)
    {
        return $query->where('active', 1);
    }

    /**
     * Scope a query to only include top level categories.
     *
     * @param  Builder  $query
     * @return Builder
     */
    public function scopeTopLevel($query)
    {
        return $query->whereNull('parent_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transactionItem()
    {
        return $this->hasMany(TransactionItem::class);
    }

    public function transaction()
    {
        return $this->hasManyThrough(Transaction::class, TransactionItem::class, 'category_id', 'id', 'id', 'transaction_id');
    }

    public function payeesNotPreferring()
    {
        return $this->belongsToMany(AccountEntity::class, 'account_entity_category_preference')->where('preferred', false);
    }
}
