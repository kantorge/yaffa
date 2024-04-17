<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;

/**
 * App\Models\Payee
 *
 * @property int $id
 * @property int|null $category_id
 * @property \Illuminate\Support\Carbon|null $category_suggestion_dismissed
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $import_alias
 * @property-read Category|null $category
 * @property-read AccountEntity|null $config
 * @property-read mixed $first_transaction_date
 * @property-read mixed $latest_transaction_date
 * @property-read mixed $transaction_count
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Transaction[] $transactionsTo
 * @property-read int|null $transactions_to_count
 * @property-read User $user
 * @method static Builder|AccountEntity accounts()
 * @method static Builder|AccountEntity active()
 * @method static \Database\Factories\PayeeFactory factory(...$parameters)
 * @method static Builder|Payee newModelQuery()
 * @method static Builder|Payee newQuery()
 * @method static Builder|AccountEntity payees()
 * @method static Builder|Payee query()
 * @method static Builder|Payee whereCategoryId($value)
 * @method static Builder|Payee whereCategorySuggestionDismissed($value)
 * @method static Builder|Payee whereCreatedAt($value)
 * @method static Builder|Payee whereId($value)
 * @method static Builder|Payee whereImportAlias($value)
 * @method static Builder|Payee whereUpdatedAt($value)
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Category[] $categoryPreference
 * @property-read int|null $category_preference_count
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Category[] $deferredCategories
 * @property-read int|null $deferred_categories_count
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Category[] $preferredCategories
 * @property-read int|null $preferred_categories_count
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\TransactionDetailStandard[] $transactionDetailStandardFrom
 * @property-read int|null $transaction_detail_standard_from_count
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\TransactionDetailStandard[] $transactionDetailStandardTo
 * @property-read int|null $transaction_detail_standard_to_count
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Transaction[] $transactionsFrom
 * @property-read int|null $transactions_from_count
 * @mixin \Eloquent
 */
class Payee extends Model
{
    use HasFactory;

    protected $guarded = [];

    public $timestamps = false;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'payees';

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'category_id',
        'category_suggestion_dismissed',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'category_suggestion_dismissed' => 'datetime',
    ];

    public function config(): MorphOne
    {
        return $this->morphOne(AccountEntity::class, 'config');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function transactions()
    {
        $account = $this;

        return Transaction::where('schedule', 0)
            ->where('budget', 0)
            ->whereHasMorph(
                'config',
                [TransactionDetailStandard::class],
                function (Builder $query) use ($account) {
                    $query->Where('account_from_id', $account->id);
                    $query->orWhere('account_to_id', $account->id);
                }
            )
            ->get();
    }

    public function getLatestTransactionDateAttribute()
    {
        return $this->transactions()->pluck('date')->max();
    }

    public function getFirstTransactionDateAttribute()
    {
        return $this->transactions()->pluck('date')->min();
    }

    public function getTransactionCountAttribute()
    {
        return $this->transactions()->count();
    }
}
