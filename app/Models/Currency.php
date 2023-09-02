<?php

namespace App\Models;

use AmrShawky\LaravelCurrency\Facade\Currency as CurrencyApi;
use App\Http\Traits\ModelOwnedByUserTrait;
use Carbon\Carbon;
use Database\Factories\CurrencyFactory;
use Eloquent;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

/**
 * App\Models\Currency
 *
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property string $iso_code
 * @property int $num_digits
 * @property bool|null $base
 * @property bool $auto_update
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read User $user
 * @method static Builder|Currency autoUpdate()
 * @method static Builder|Currency base()
 * @method static CurrencyFactory factory(...$parameters)
 * @method static Builder|Currency newModelQuery()
 * @method static Builder|Currency newQuery()
 * @method static Builder|Currency notBase()
 * @method static Builder|Currency query()
 * @method static Builder|Currency whereAutoUpdate($value)
 * @method static Builder|Currency whereBase($value)
 * @method static Builder|Currency whereCreatedAt($value)
 * @method static Builder|Currency whereId($value)
 * @method static Builder|Currency whereIsoCode($value)
 * @method static Builder|Currency whereName($value)
 * @method static Builder|Currency whereNumDigits($value)
 * @method static Builder|Currency whereUpdatedAt($value)
 * @method static Builder|Currency whereUserId($value)
 * @mixin Eloquent
 */
class Currency extends Model
{
    use HasFactory;
    use ModelOwnedByUserTrait;

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
     * @param Builder $query
     * @return Builder
     */
    public function scopeBase(Builder $query): Builder
    {
        return $query->where('base', true);
    }

    /**
     * Create a scope for the query to only return currencies that are not base currencies.
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeNotBase(Builder $query): Builder
    {
        return $query->whereNull('base');
    }

    /**
     * Create a scope for the query to only return currencies that are set to be automatically updated.
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeAutoUpdate(Builder $query): Builder
    {
        return $query->where('auto_update', true);
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
        return self::base()->where('user_id', $this->user_id)
            ->firstOr(fn () => self::where('user_id', $this->user_id)->orderBy('id')->firstOr(fn () => null));
    }

    /**
     * Get the currency rates for this currency.
     *
     * @param Carbon|null $from
     */
    public function retreiveCurrencyRateToBase(?Carbon $from = null): void
    {
        $baseCurrency = $this->baseCurrency();

        if ($baseCurrency === null || $baseCurrency->id === $this->id) {
            return;
        }

        $date = Carbon::parse('yesterday');
        if (!$from) {
            $from = Carbon::parse('yesterday');
        }

        $rates = CurrencyApi::rates()
            ->timeSeries($from->format('Y-m-d'), $date->format('Y-m-d'))
            ->base($this->iso_code)
            ->symbols([$baseCurrency->iso_code])
            ->get();

        // If rates is not an array with at least one element, return.
        if (!is_array($rates) || count($rates) === 0) {
            return;
        }

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
     */
    public function retreiveMissingCurrencyRateToBase(): void
    {
        $baseCurrency = $this->baseCurrency();

        if ($baseCurrency === null || $baseCurrency->id === $this->id) {
            return;
        }

        // Get the latest date for this currency, compared to the base currency.
        $rate = CurrencyRate::where('from_id', $this->id)
            ->where('to_id', $baseCurrency->id)
            ->latest('date')
            ->first();

        $this->retreiveCurrencyRateToBase(
            $rate?->date ?? Carbon::parse('30 days ago') // Fallback to last 30 days
        );
    }

    public function setToBase(): bool
    {
        $baseCurrency = $this->baseCurrency();

        if ($baseCurrency->id === $this->id) {
            return false;
        }

        try {
            DB::beginTransaction();

            Currency::where('user_id', $this->user->id)->update('base', null);
            $this->base = true;
            $this->save();

            DB::commit();
        } catch (Exception $e) {
            DB::rollback();
            return false;
        }

        return true;
    }
}
