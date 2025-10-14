<?php

namespace App\Models;

use App\Exceptions\CurrencyRateConversionException;
use App\Http\Traits\ModelOwnedByUserTrait;
use Carbon\Carbon;
use Database\Factories\CurrencyFactory;
use Illuminate\Database\Eloquent\Model as Eloquent;
use Exception;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Kantorge\CurrencyExchangeRates\Facades\CurrencyExchangeRates;

/**
 * App\Models\Currency
 *
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property string $iso_code
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
     * @var array<string>
     */
    protected $fillable = [
        'name',
        'iso_code',
        'base',
        'auto_update',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'base' => 'boolean',
            'auto_update' => 'boolean',
        ];
    }

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
    #[Scope]
    protected function base(Builder $query): Builder
    {
        return $query->where('base', true);
    }

    /**
     * Create a scope for the query to only return currencies that are not base currencies.
     *
     * @param Builder $query
     * @return Builder
     */
    #[Scope]
    protected function notBase(Builder $query): Builder
    {
        return $query->whereNull('base');
    }

    /**
     * Create a scope for the query to only return currencies that are set to be automatically updated.
     *
     * @param Builder $query
     * @return Builder
     */
    #[Scope]
    protected function autoUpdate(Builder $query): Builder
    {
        return $query->where('auto_update', true);
    }

    /**
     * Get the latest currency rate for this currency, compared to the base currency.
     * If no currency rate exists, return null.
     *
     * @return float|null
     */
    public function rate(): ?float
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
        return static::query()
            ->base()
            ->where('user_id', $this->user_id)
            ->firstOr(fn () => static::query()->where('user_id', $this->user_id)->orderBy('id')->firstOr(fn () => null));
    }

    /**
     * Get and save the currency rates for this currency against the base currency.
     *
     * @param Carbon|null $dateFrom
     * @throws CurrencyRateConversionException
     * @throws Exception
     */
    public function retrieveCurrencyRateToBase(?Carbon $dateFrom = null): void
    {
        $baseCurrency = $this->baseCurrency();

        // This should never happen, as the base currency falls back to the first currency created by the user.
        if ($baseCurrency === null) {
            throw new CurrencyRateConversionException('Base currency not found');
        }

        if ($baseCurrency->id === $this->id) {
            throw new CurrencyRateConversionException('Currency is the same as the base currency');
        }

        $date = Carbon::parse('yesterday');
        if (!$dateFrom) {
            $dateFrom = Carbon::parse('yesterday');
        }

        $currencyApi = CurrencyExchangeRates::create();

        // Verify that both currencies are supported by the API.
        if (!$currencyApi->isCurrencySupported($this->iso_code) || !$currencyApi->isCurrencySupported($baseCurrency->iso_code)) {
            throw new CurrencyRateConversionException('One or more of the currencies are not supported by the API');
        }

        $apiData = $currencyApi->getTimeSeries(
            $dateFrom,
            $date,
            $this->iso_code,
            [$baseCurrency->iso_code]
        );

        // If rates is not an array with at least one element, throw an exception.
        if (!is_array($apiData) || empty($apiData)) {
            throw new CurrencyRateConversionException('No data returned from the API');
        }

        $validRates = [];

        foreach ($apiData as $date => $rate) {
            $sanitizedRate = (float) $rate[$baseCurrency->iso_code];

            // Check if the rate is within the valid range.
            if ($sanitizedRate <= 0 || $sanitizedRate >= 9999999999.9999999999) {
                throw new CurrencyRateConversionException('Currency rate is out of the valid range');
            }

            $validRates[$date] = $sanitizedRate;
        }

        // Save the rates to the database
        foreach ($validRates as $date => $sanitizedRate) {
            CurrencyRate::updateOrCreate(
                [
                    'from_id' => $this->id,
                    'to_id' => $baseCurrency->id,
                    'date' => $date,
                ],
                [
                    'rate' => $sanitizedRate,
                ]
            );
        }
    }

    /**
     * Get all the missing currency rates for this currency.
     * @throws CurrencyRateConversionException
     */
    public function retrieveMissingCurrencyRateToBase(): void
    {
        $baseCurrency = $this->baseCurrency();

        // This should never happen, as the base currency falls back to the first currency created by the user.
        if ($baseCurrency === null) {
            throw new CurrencyRateConversionException('Base currency not found');
        }

        if ($baseCurrency->id === $this->id) {
            throw new CurrencyRateConversionException('Currency is the same as the base currency');
        }

        // Get the latest date for this currency, compared to the base currency.
        $rate = CurrencyRate::where('from_id', $this->id)
            ->where('to_id', $baseCurrency->id)
            ->latest('date')
            ->first();

        $this->retrieveCurrencyRateToBase(
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

            Currency::where('user_id', $this->user->id)
                ->update(['base' => null]);
            $this->base = true;
            $this->save();

            DB::commit();

            Cache::forget("allCurrencyRatesByMonth_forUser_{$this->user->id}");
        } catch (Exception $e) {
            DB::rollback();
            return false;
        }

        return true;
    }
}
