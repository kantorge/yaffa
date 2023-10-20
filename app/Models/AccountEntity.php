<?php

namespace App\Models;

use Database\Factories\AccountEntityFactory;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

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
 *
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
 * @method static Builder|AccountEntity forCurrentUser()
 *
 * @property string|null $alias
 * @property-read Collection<int, Transaction> $transactionsInvestment
 * @property-read int|null $transactions_investment_count
 * @property-read Collection<int, Transaction> $transactionsStandardFrom
 * @property-read int|null $transactions_standard_from_count
 * @property-read Collection<int, Transaction> $transactionsStandardTo
 * @property-read int|null $transactions_standard_to_count
 *
 * @method static Builder|AccountEntity investments()
 * @method static Builder|AccountEntity whereAlias($value)
 *
 * @mixin Eloquent
 */
class AccountEntity extends Model
{
    use HasFactory;

    protected $table = 'account_entities';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
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

    protected $casts = [
        'active' => 'boolean',
    ];

    public function config(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Returns the transactions where the TransactionType is investment,
     * and account_from_id or account_to_id is this account entity.
     */
    public function transactionsInvestment(): HasMany
    {
        return $this->hasMany(
            Transaction::class,
            'account_from_id'
        )
            ->orWhere(
                'account_to_id',
                $this->id
            )
            ->whereHas(
                'transactionType',
                fn ($query) => $query->where('type', 'investment')
            );

    }

    /**
     * Returns the transactions where the TransactionType is standard,
     * and account_from_id is this account entity.
     */
    public function transactionsStandardFrom(): HasMany
    {
        return $this->hasMany(
            Transaction::class,
            'account_from_id'
        )
            ->whereHas(
                'transactionType',
                fn ($query) => $query->where('type', 'standard')
            );
    }

    /**
     * Returns the transactions where the TransactionType is standard,
     * and account_to_id is this account entity.
     */
    public function transactionsStandardTo(): HasMany
    {
        return $this->hasMany(
            Transaction::class,
            'account_to_id'
        )->whereHas(
            'transactionType',
            fn ($query) => $query->where('type', 'standard')
        );
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
     *
     * @param  Builder  $query
     * @return Builder
     */
    public function scopeActive($query)
    {
        return $query->where('active', 1);
    }

    /**
     * Scope a query to only include accounts.
     *
     * @param  Builder  $query
     */
    public function scopeAccounts($query): Builder
    {
        return $query->where('config_type', 'account');
    }

    /**
     * Scope a query to only include payees.
     *
     * @param  Builder  $query
     */
    public function scopePayees($query): Builder
    {
        return $query->where('config_type', 'payee');
    }

    /**
     * Scope a query to only include investments.
     *
     * @param  Builder  $query
     */
    public function scopeInvestments($query): Builder
    {
        return $query->where('config_type', 'investment');
    }

    /**
     * Scope a query to only include account entities of authenticated user.
     *
     * @param  Builder  $query
     */
    public function scopeForCurrentUser($query): Builder
    {
        return $query->where('user_id', Auth::user()->id);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
