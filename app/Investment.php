<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

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
        'investment_groups_id',
        'currencies_id',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    protected $with = [
        'investment_groups',
        'currencies',
    ];

    public function investment_groups()
    {
        return $this->belongsTo(InvestmentGroup::class);
    }

    public function currencies()
    {
        return $this->belongsTo(Currency::class);
    }
}
