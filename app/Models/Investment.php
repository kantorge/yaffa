<?php

namespace App\Models;

use App\Models\InvestmentGroup;
use App\Models\InvestmentPrice;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Investment extends Model
{

    protected $guarded = [];

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'investments';

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
        'name',
        'symbol',
        'comment',
        'active',
        'auto_update',
        'investment_group_id',
        'currency_id',
        'investment_price_provider_id',
    ];

    protected $casts = [
        'active' => 'boolean',
        'auto_update' => 'boolean',
    ];

    protected $with = [
        'investment_group',
        'currency',
        'investment_price_provider'
    ];

    public function investment_group()
    {
        return $this->belongsTo(InvestmentGroup::class);
    }

    public function currency()
    {
        return $this->belongsTo(Currency::class);
    }

    public function investment_price_provider()
    {
        return $this->belongsTo(InvestmentPriceProvider::class);
    }

    public function getCurrentQuantity(AccountEntity $account = null)
    {
        $investmentId = $this->id;

        //get all investment transactions for current investment
        $transactions = Transaction::with(
            [
                'config',
                'transactionType',
            ]
        )
        ->where('schedule', 0)
        ->where('budget', 0)
        ->where('config_type', 'transaction_detail_investment')
        ->whereHasMorph(
            'config',
            [\App\Models\TransactionDetailInvestment::class],
            function (Builder $query) use ($investmentId, $account) {
                $query->Where('investment_id', $investmentId);
                if (!is_null($account)) {
                    $query->where('account_id', '=', $account->id);
                }
            }
        )
        ->get();

        return $transactions->sum(function ($transaction) {
            $operator = $transaction->transactionType->quantity_operator;
            if (!$operator) {
                return 0;
            }

            return $transaction->config->quantity * ($operator === 'minus' ? -1 : 1);
        });
    }

    public function getLatestPrice($type = 'combined')
    {
        $investmentId = $this->id;

        if ($type === 'stored' || $type === 'combined') {
            $price = InvestmentPrice::where('investment_id', $investmentId)
                                        ->latest('date')
                                        ->first();
        }

        if ($type === 'transaction' || $type === 'combined') {
            $transaction = Transaction::with(
                [
                    'config',
                    'transactionType',
                ]
            )
            ->where('schedule', 0)
            ->where('budget', 0)
            ->whereHasMorph(
                'config',
                [\App\Models\TransactionDetailInvestment::class],
                function (Builder $query) use ($investmentId) {
                    $query
                        ->Where('investment_id', $investmentId)
                        ->WhereNotNull('price');
                }
            )
            ->latest('date')
            ->first();
        }

        if ($type === 'stored') {
            return $price instanceof InvestmentPrice ? $price->price : null;
        }

        if ($type === 'transaction') {
            return $transaction instanceof Transaction ? $transaction->config->price : null;
        }

        //combined is needed and we have both data: get latest
        if (($price instanceof InvestmentPrice) && ($transaction instanceof Transaction)) {
            if ($price->date > $transaction->date) {
                return $price->price;
            }

            return $transaction->config->price;
        }

        //we have only stored data
        if ($price instanceof InvestmentPrice) {
            return $price->price;
        }

        //we have only transaction data
        if ($transaction instanceof Transaction) {
            return $transaction->config->price;
        }

        return null;
    }
}