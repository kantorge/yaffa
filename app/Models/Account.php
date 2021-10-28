<?php

namespace App\Models;

use App\Models\AccountEntity;
use App\Models\AccountGroup;
use App\Models\Currency;
use Illuminate\Database\Eloquent\Builder;

class Account extends AccountEntity
{
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

    public function account_group()
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
        $accountId = $this->id;

        //get all investment transactions for current investment
        $transactions = Transaction::with(
            [
                'config',
                'config.investment',
                'transactionType',
            ]
        )
        ->where('schedule', 0)
        ->where('budget', 0)
        ->where('config_type', 'transaction_detail_investment')
        ->whereHasMorph(
            'config',
            [\App\Models\TransactionDetailInvestment::class],
            function (Builder $query) use ($accountId) {
                $query->where('account_id', '=', $accountId);
            }
        )
        ->get();

        return $transactions
            ->map(function ($transaction) {
                $operator = $transaction->transactionType->quantity_operator;
                if (! $operator) {
                    $quantity = 0;
                } else {
                    $quantity = ($operator === 'minus'
                                    ? -$transaction->config->quantity
                                    : $transaction->config->quantity);
                }

                return [
                        'investment' => $transaction->config->investment,
                        'quantity' => $quantity,
                    ];
            })
            ->groupBy('investment.id')
            ->map(function ($investment, $key) {
                return [
                    'investment' => $key,
                    'quantity' => $investment->sum('quantity'),
                ];
            });
    }
}
