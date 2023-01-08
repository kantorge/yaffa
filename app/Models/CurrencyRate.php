<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CurrencyRate extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'currency_rates';

    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'date',
        'from_id',
        'to_id',
        'rate',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'date' => 'datetime:Y-m-d',
        // TODO: Add proper cast type for 'rate'
    ];

    public function currencyFrom()
    {
        return $this->belongsTo(Currency::class, 'from_id');
    }

    public function currencyTo()
    {
        return $this->belongsTo(Currency::class, 'to_id');
    }
}
