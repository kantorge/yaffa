<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
     * @var array<string>
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
     * @var array<string, string>
     */
    protected $casts = [
        'date' => 'datetime:Y-m-d',
        'rate' => 'float',
    ];

    public function currencyFrom(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'from_id');
    }

    public function currencyTo(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'to_id');
    }


}
