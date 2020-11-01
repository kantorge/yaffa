<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class Transaction extends Model
{

    //protected $softDelete = true;

    //protected $dates = ['deleted_at'];

    protected $table = 'transactions';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'date',
        'transaction_type_id',
        'reconciled',
        'is_schedule',
        'is_budget',
        'comment',
        'config_type',
        'config_id'
    ];

    protected $hidden = ['config_id'];

    //protected $with = ['config'];

    protected $casts = [
        'active' => 'reconciled',
        'active' => 'is_schedule',
        'active' => 'is_budget',
    ];

    public function config()
    {
        return $this->morphTo();
    }

    public function transactionType()
    {
        return $this->belongsTo(TransactionType::class);
    }

    public function transactionItems()
    {
        return $this->hasMany(TransactionItem::class);
    }

    /*
    public function transactionDetailsStandard()
    {
        return $this->hasMany(TransactionDetailStandard::class);
    }
    */
}
