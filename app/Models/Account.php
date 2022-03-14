<?php

namespace App\Models;

use App\Models\AccountEntity;
use App\Models\AccountGroup;
use App\Models\Currency;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class Account extends AccountEntity
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

    public function accountGroup()
    {
        return $this->belongsTo(AccountGroup::class);
    }

    public function currency()
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
                ->where('type', 'Investment')
                ->whereNotNull('quantity_operator');
            })
            ->where('transaction_details_investment.account_id', $this->config->id)
            ->get();
    }
}
