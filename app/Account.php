<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Validation\Rule;

class Account extends AccountEntity
{
    protected $guarded = [];

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

    public function openingBalance() {
        return [
            'id' => null,
            'date' => null,
            'transaction_name' => 'Opening balance',
            'transaction_type' => 'Opening balance',
            'transaction_operator' => 'plus',
            'account_from_id' => null,
            'account_from_name' => null,
            'account_to_id' => null,
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
}
