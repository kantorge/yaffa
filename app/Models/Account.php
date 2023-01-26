<?php

namespace App\Models;

use Database\Factories\AccountFactory;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * App\Models\Account
 *
 * @property int $id
 * @property int $opening_balance
 * @property int $account_group_id
 * @property int $currency_id
 * @property-read AccountGroup $accountGroup
 * @property-read Collection|Category[] $categoryPreference
 * @property-read int|null $category_preference_count
 * @property-read AccountEntity|null $config
 * @property-read Currency $currency
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
 * @property-read User|null $user
 * @method static Builder|AccountEntity accounts()
 * @method static Builder|AccountEntity active()
 * @method static AccountFactory factory(...$parameters)
 * @method static Builder|Account newModelQuery()
 * @method static Builder|Account newQuery()
 * @method static Builder|AccountEntity payees()
 * @method static Builder|Account query()
 * @method static Builder|Account whereAccountGroupId($value)
 * @method static Builder|Account whereCurrencyId($value)
 * @method static Builder|Account whereId($value)
 * @method static Builder|Account whereOpeningBalance($value)
 * @mixin Eloquent
 */
class Account extends Model
{
    use HasFactory;

    protected $guarded = [];

    public $timestamps = false;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'accounts';

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'opening_balance',
        'account_group_id',
        'currency_id',
    ];

    public function config()
    {
        return $this->morphOne(AccountEntity::class, 'config');
    }

    public function accountGroup(): BelongsTo
    {
        return $this->belongsTo(AccountGroup::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function openingBalance()
    {
        return (object) [
            'id' => null,
            'date' => null,
            'transaction_type' => [
                'name' => 'Opening balance',
                'type' => 'Opening balance',
            ],
            'transactionOperator' => 'plus',
            'config' => [
                'account_from_id' => null,
                'account_to_id' => null,
            ],
            'account_from_name' => null,
            'account_to_name' => null,
            'amount_from' => 0,
            'amount_to' => $this->opening_balance,
            'tags' => [],
            'categories' => [],
            'reconciled' => 0,
            'comment' => null,
            'edit_url' => null,
            'delete_url' => null,
        ];
    }

    public function getAssociatedInvestmentsAndQuantity()
    {
        return DB::table('transactions')
            ->select(
                'transaction_details_investment.investment_id',
                DB::raw('sum(CASE WHEN transaction_types.quantity_operator = "plus" THEN 1 ELSE -1 END * IFNULL(transaction_details_investment.quantity, 0)) AS quantity')
            )
            ->groupBy(
                'transaction_details_investment.investment_id',
            )
            ->leftJoin('transaction_details_investment', 'transactions.config_id', '=', 'transaction_details_investment.id')
            ->leftJoin('transaction_types', 'transactions.transaction_type_id', '=', 'transaction_types.id')
            ->where('transactions.user_id', Auth::user()->id)
            ->where('transactions.schedule', 0)
            ->where('transactions.budget', 0)
            ->where('transactions.config_type', 'transaction_detail_investment')
            ->whereIn('transactions.transaction_type_id', function ($query) {
                $query->from('transaction_types')
                ->select('id')
                ->where('type', 'investment')
                ->whereNotNull('quantity_operator');
            })
            ->where('transaction_details_investment.account_id', $this->config->id)
            ->get();
    }
}
