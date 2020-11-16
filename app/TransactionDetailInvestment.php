<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class TransactionDetailInvestment extends Transaction
{
    protected $table = 'transaction_details_investment';

    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'account_id',
        'investment_id',
        'price',
        'quantity',
        'commission',
        'dividend',
    ];

    protected $casts = [
        'account_id' => 'integer',
        'investment_id' => 'integer',
    ];

    public function config()
    {
        return $this->morphOne(Transaction::class, 'config');
    }

    public function account()
    {
        return $this->belongsTo(AccountEntity::class, 'account_id');
    }

    public function investment()
    {
        return $this->belongsTo(Investment::class, 'investment_id');
    }

}
