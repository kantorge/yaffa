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
        'account_groups_id',
        'currencies_id',
    ];

    public static $labels = [
        'opening_balance' => 'Opening balance',
        'account_group' => 'Account group',
        'currency' => 'Currency',
    ];

    public function config()
    {
        return $this->morphOne(AccountEntity::class, 'config');
    }

    public function account_groups()
    {
        return $this->belongsTo(AccountGroup::class);
    }

    public function currencies()
    {
        return $this->belongsTo(Currency::class);
    }
}
