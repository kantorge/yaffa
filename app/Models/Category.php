<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use App\Http\Traits\ModelOwnedByUserTrait;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * App\Models\Category
 *
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property bool $active
 * @property int|null $parent_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read mixed $full_name
 * @property-read Category|null $parent
 * @property-read Collection|AccountEntity[] $payeesNotPreferring
 * @property-read int|null $payees_not_preferring_count
 * @property-read Collection|Transaction[] $transaction
 * @property-read int|null $transaction_count
 * @property-read Collection|TransactionItem[] $transactionItem
 * @property-read int|null $transaction_item_count
 * @property-read User $user
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
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'active',
        'parent_id',
        'default_aggregation',
    ];

    protected $appends = [
        'full_name',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class);
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function getFullNameAttribute(): string
    {
        return (isset($this->parent->name) ? $this->parent->name . ' > ' : '') . $this['name'];
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

    /**
     * Scope a query to only include top level categories only.
     *
     * @param Builder $query
     * @return Builder
     */
    #[Scope]
    protected function parentCategory(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }

    /**
     * Scope a query to only include child categories only.
     *
     * @param Builder $query
     * @return Builder
     */
    #[Scope]
    protected function childCategory(Builder $query): Builder
    {
        return $query->whereNotNull('parent_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transactionItem(): HasMany
    {
        return $this->hasMany(TransactionItem::class);
    }

    public function transaction(): HasManyThrough
    {
        return $this->hasManyThrough(
            Transaction::class,
            TransactionItem::class,
            'category_id',
            'id',
            'id',
            'transaction_id'
        );
    }

    public function payeesNotPreferring(): BelongsToMany
    {
        return $this->belongsToMany(
            AccountEntity::class,
            'account_entity_category_preference'
        )->where('preferred', false);
    }

    public function payeesPreferring(): BelongsToMany
    {
        return $this->belongsToMany(
            AccountEntity::class,
            'account_entity_category_preference'
        )->where('preferred', true);
    }

    public function payeesDefaulting(): HasManyThrough
    {
        return $this->hasManyThrough(
            AccountEntity::class,
            Payee::class,
            'category_id',
            'config_id',
            'id',
            'id'
        )
            ->where(
                'config_type',
                'payee'
            );
    }
}
