<?php

namespace App\Models;

use App\Models\AccountEntity;
use App\Models\Investment;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TransactionDetailInvestment extends Transaction
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
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
        'tax',
        'dividend',
    ];

    protected $casts = [
        'account_id' => 'integer',
        'investment_id' => 'integer',
        'price' => 'float',
        'quantity' => 'float',
        'commission' => 'float',
        'tax' => 'float',
        'dividend' => 'float',
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
