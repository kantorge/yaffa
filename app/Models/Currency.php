<?php

namespace App\Models;

use AmrShawky\LaravelCurrency\Facade\Currency as CurrencyApi;
use App\Http\Traits\ModelOwnedByUserTrait;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Currency extends Model
{
    use HasFactory, ModelOwnedByUserTrait;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'currencies';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'iso_code',
        'num_digits',
        'suffix',
        'base',
        'auto_update',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'base' => 'boolean',
        'auto_update' => 'boolean',
    ];

    /**
     * Get the user that owns this currency.
     *
     * @return BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Create a scope for the query to only return base currencies.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeBase($query)
    {
        return $query->where('base', true);
    }

    /**
     * Create a scope for the query to only return currencies that are not base currencies.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeNotBase($query)
    {
        return $query->whereNull('base');
    }

    /**
     * Get the latest currency rate for this currency, compared to the base currency.
     * If no currency rate exists, return null.
     *
     * @return string|null
     */
    public function rate(): ?string
    {
        $baseCurrency = $this->baseCurrency();
        if ($baseCurrency === null || $baseCurrency->id === $this->id) {
            return null;
        }

        // Get the last known currency rate for this currency, compared to the base currency.
        $rate = CurrencyRate::where('from_id', $this->id)
                                    ->where('to_id', $baseCurrency->id)
                                    ->latest('date')
                                    ->first();

        return $rate instanceof CurrencyRate ? $rate->rate : null;
    }

    /**
     * Get the base currency of the same user, who owns this currency.
     *
     * @return Currency|null
     */
    public function baseCurrency(): ?Currency
    {
        return self::base()->where('user_id', $this->user_id)->firstOr(function () {
            return self::where('user_id', $this->user_id)->orderBy('id')->firstOr(function () {
                return null;
            });
        });
    }

    /**
     * Get the currency rates for this currency.
     *
     * @param  Carbon|null  $from
     * @return void
     */
    public function retreiveCurrencyRateToBase(?Carbon $from = null): void
    {
        $baseCurrency = $this->baseCurrency();

        if ($baseCurrency === null || $baseCurrency->id === $this->id) {
            // TODO: is an exception needed?
            return;
        }

        $date = Carbon::create('yesterday');
        if (! $from) {
            $from = Carbon::create('yesterday');
        }

        $rates = CurrencyApi::rates()
            ->timeSeries($from->format('Y-m-d'), $date->format('Y-m-d'))
            ->base($this->iso_code)
            ->symbols([$baseCurrency->iso_code])
            ->get();

        foreach ($rates as $date => $rate) {
            CurrencyRate::updateOrCreate(
                [
                    'from_id' => $this->id,
                    'to_id' => $baseCurrency->id,
                    'date' => $date,
                ],
                [
                    'rate' => $rate[$baseCurrency->iso_code],
                ]
            );
        }
    }

    /**
     * Get all the missing currency rates for this currency.
     *
     * @return void
     */
    public function retreiveMissingCurrencyRateToBase(): void
    {
        $baseCurrency = $this->baseCurrency();

        if ($baseCurrency === null || $baseCurrency->id === $this->id) {
            // TODO: is an exception needed?
            return;
        }

        // Get the latest date for this currency, compared to the base currency.
        $rate = CurrencyRate::where('from_id', $this->id)
                                    ->where('to_id', $baseCurrency->id)
                                    ->latest('date')
                                    ->first();

        $this->retreiveCurrencyRateToBase(
            $rate?->date ?? Carbon::create('30 days ago') // Fallback to last 30 days
        );
    }
}
