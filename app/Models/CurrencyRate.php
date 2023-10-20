<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\CurrencyRate
 *
 * @property int $id
 * @property int $from_id
 * @property int $to_id
 * @property \Illuminate\Support\Carbon $date
 * @property string $rate
 * @property-read \App\Models\Currency $currencyFrom
 * @property-read \App\Models\Currency $currencyTo
 *
 * @method static \Illuminate\Database\Eloquent\Builder|CurrencyRate newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|CurrencyRate newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|CurrencyRate query()
 * @method static \Illuminate\Database\Eloquent\Builder|CurrencyRate whereDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CurrencyRate whereFromId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CurrencyRate whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CurrencyRate whereRate($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CurrencyRate whereToId($value)
 *
 * @mixin \Eloquent
 */
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
