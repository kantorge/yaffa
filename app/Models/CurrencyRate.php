<?php

namespace App\Models;

use App\Observers\CurrencyRateObserver;
use Database\Factories\CurrencyRateFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[ObservedBy([CurrencyRateObserver::class])]
class CurrencyRate extends Model
{
    use HasFactory;

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
