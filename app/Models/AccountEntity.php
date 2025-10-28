<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Database\Factories\AccountEntityFactory;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * App\Models\AccountEntity
 *
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property bool $active
 * @property string $config_type
 * @property int $config_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection|Category[] $categoryPreference
 * @property-read int|null $category_preference_count
 * @property-read Model|Eloquent $config
 * @property-read Collection|Category[] $deferredCategories
 * @property-read int|null $deferred_categories_count
 * @property-read Collection|Category[] $preferredCategories
 * @property-read int|null $preferred_categories_count
 * @property-read Collection|TransactionDetailStandard[] $transactionDetailStandardFrom
 * @property-read int|null $transaction_detail_standard_from_count
 * @property-read Collection|TransactionDetailStandard[] $transactionDetailStandardTo
 * @property-read int|null $transaction_detail_standard_to_count
 * @property-read Collection|Transaction[] $transactionsFrom
 * @property-read int|null $transactions_from_count
 * @property-read Collection|Transaction[] $transactionsTo
 * @property-read int|null $transactions_to_count
 * @property-read User $user
 * @method static Builder|AccountEntity accounts()
 * @method static Builder|AccountEntity active()
 * @method static AccountEntityFactory factory(...$parameters)
 * @method static Builder|AccountEntity newModelQuery()
 * @method static Builder|AccountEntity newQuery()
 * @method static Builder|AccountEntity payees()
 * @method static Builder|AccountEntity query()
 * @method static Builder|AccountEntity whereActive($value)
 * @method static Builder|AccountEntity whereConfigId($value)
 * @method static Builder|AccountEntity whereConfigType($value)
 * @method static Builder|AccountEntity whereCreatedAt($value)
 * @method static Builder|AccountEntity whereId($value)
 * @method static Builder|AccountEntity whereName($value)
 * @method static Builder|AccountEntity whereUpdatedAt($value)
 * @method static Builder|AccountEntity whereUserId($value)
 * @mixin Eloquent
 */
class AccountEntity extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'active',
        'config_type',
        'config_id',
        'user_id',
        'alias',
    ];

    protected $hidden = ['config_id'];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }

    public function config(): MorphTo
    {
        return $this->morphTo();
    }

    public function transactionsInvestment(): HasManyThrough
    {
        return $this->hasManyThrough(
            Transaction::class,
            TransactionDetailInvestment::class,
            'account_id',
            'config_id',
            'id',
            'id',
        )
            ->where(
                'config_type',
                'investment'
            );
    }

    public function transactionsStandardFrom(): HasManyThrough
    {
        return $this->hasManyThrough(
            Transaction::class,
            TransactionDetailStandard::class,
            'account_from_id',
            'config_id',
            'id',
            'id',
        )
            ->where(
                'config_type',
                'standard'
            );
    }

    public function transactionsStandardTo(): HasManyThrough
    {
        return $this->hasManyThrough(
            Transaction::class,
            TransactionDetailStandard::class,
            'account_to_id',
            'config_id',
            'id',
            'id',
        )
            ->where(
                'config_type',
                'standard'
            );
    }

    public function allTransactionDates(): Builder
    {
        $transactionsInvestment = $this->transactionsInvestment()->select('date')->getQuery();
        $transactionsStandardFrom = $this->transactionsStandardFrom()->select('date')->getQuery();
        $transactionsStandardTo = $this->transactionsStandardTo()->select('date')->getQuery();

        return $transactionsInvestment->union($transactionsStandardFrom)->union($transactionsStandardTo);
    }

    /**
     * Relationship to categories indicating the category preference for this account entity.
     * Relevant mainly for payees.
     */
    public function categoryPreference(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'account_entity_category_preference')
            ->withPivot('preferred');
    }

    public function preferredCategories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'account_entity_category_preference')
            ->where('preferred', true);
    }

    public function deferredCategories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'account_entity_category_preference')
            ->where('preferred', false);
    }

    /**
     * Scope a query to only include active entities.
     */
    #[Scope]
    protected function active(Builder $query): Builder
    {
        return $query->where('active', 1);
    }

    /**
     * Scope a query to only include accounts.
     */
    #[Scope]
    protected function accounts(Builder $query): Builder
    {
        return $query->where('config_type', 'account');
    }

    /**
     * Scope a query to only include payees.
     */
    #[Scope]
    protected function payees(Builder $query): Builder
    {
        return $query->where('config_type', 'payee');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isAccount(): bool
    {
        return $this->config_type === 'account';
    }

    public function isPayee(): bool
    {
        return $this->config_type === 'payee';
    }
}
