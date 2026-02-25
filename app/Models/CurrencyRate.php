<?php

namespace App\Models;

use App\Observers\CurrencyRateObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[ObservedBy([CurrencyRateObserver::class])]
/**
 * @property int $id
 * @property int $from_id
 * @property int $to_id
 * @property \Illuminate\Support\Carbon $date
 * @property float $rate
 * @property-read Currency $currencyFrom
 * @property-read Currency $currencyTo
 * @method static \Database\Factories\CurrencyRateFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CurrencyRate newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CurrencyRate newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CurrencyRate query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CurrencyRate whereDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CurrencyRate whereFromId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CurrencyRate whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CurrencyRate whereRate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CurrencyRate whereToId($value)
 * @mixin \Eloquent
 */
class CurrencyRate extends Model
{
    use HasFactory;

    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'date',
        'from_id',
        'to_id',
        'rate',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'datetime:Y-m-d',
            'rate' => 'float',
        ];
    }

    public function currencyFrom(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'from_id');
    }

    public function currencyTo(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'to_id');
    }


}
