<?php

namespace App\Models;

use App\Models\AccountEntity;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TransactionDetailStandard extends Transaction
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
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

    protected $casts = [
        'amount_from' => 'float',
        'amount_to' => 'float',
    ];

    public function config()
    {
        return $this->morphOne(Transaction::class, 'config');
    }

    public function accountFrom()
    {
        return $this->belongsTo(AccountEntity::class, 'account_from_id');
    }

    public function accountTo()
    {
        return $this->belongsTo(AccountEntity::class, 'account_to_id');
    }
}