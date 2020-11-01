<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class TransactionDetailStandard extends Transaction
{
    protected $table = 'transaction_details_standard';

    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'account_from_id',
        'account_to_id',
        'amount_from',
        'amount_to',
    ];

    public function config()
    {
        return $this->morphOne(Transaction::class, 'config');
    }

}
