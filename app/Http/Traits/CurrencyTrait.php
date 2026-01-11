<?php

namespace App\Http\Traits;

use App\Models\Currency;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

trait CurrencyTrait
{
    /**
     * Load an array for all currencies, with an average rate by month
     * As this data is not expected to change often, it is cached for a day
     */
    public function allCurrencyRatesByMonth(): array
    {
        // If, for any reason, we cannot retrieve the base currency, we return an empty array
        $baseCurrency = $this->getBaseCurrency();
        if (!$baseCurrency) {
            return [];
        }

        $userId = auth()->user()->id;
        $cacheKey = "allCurrencyRatesByMonth_forUser_{$userId}";

        return Cache::remember($cacheKey, now()->addDay(), function () use ($baseCurrency) {
            $rates = DB::table('currency_rates')
                ->select(
                    DB::raw('SUBDATE(`date`, (DAY(`date`)-1)) AS `month`'),
                    'from_id',
                    DB::raw('AVG(rate) AS rate')
                )
                // Rates are retrieved only towards the base currency, which also defines the user
                ->where('to_id', '=', $baseCurrency->id)
                ->groupBy(
                    DB::raw('SUBDATE(`date`, (DAY(`date`)-1))'),
                    'from_id'
                )
                ->orderBy('from_id')
                ->orderByDesc('month')
                ->get();

            // Pre-process the $rates collection into a map array
            $allRatesMap = [];
            foreach ($rates as $rate) {
                $allRatesMap[$rate->from_id][$rate->month] = (float) $rate->rate;
            }

            return $allRatesMap;
        });
    }

    /**
     * Get all currencies for a specific user, cached for 24 hours.
     * Returns a collection keyed by currency ID for fast lookups.
     * Cache is automatically invalidated when currencies are modified.
     *
     * @param int|null $userId User ID (defaults to authenticated user)
     * @return \Illuminate\Support\Collection<int, Currency>
     */
    public function getAllCurrencies(?int $userId = null): \Illuminate\Support\Collection
    {
        $userId = $userId ?? auth()->user()?->id;
        
        if (!$userId) {
            return collect();
        }

        $cacheKey = "currencies_user_{$userId}";

        return Cache::remember($cacheKey, now()->addHours(24), fn() =>
            Currency::where('user_id', $userId)->get()->keyBy('id')
        );
    }

    /**
     * Get base currency for a specific user.
     * Uses the cached collection from getAllCurrencies for efficiency.
     *
     * @param int|null $userId User ID (defaults to authenticated user)
     * @return Currency|null
     */
    public function getBaseCurrency(?int $userId = null): ?Currency
    {
        $userId = $userId ?? auth()->user()?->id;
        
        if (!$userId) {
            return null;
        }

        $allCurrencies = $this->getAllCurrencies($userId);

        // Try to find currency marked as base
        $baseCurrency = $allCurrencies->firstWhere('base', 1);

        // If no base currency, return first one
        return $baseCurrency ?? $allCurrencies->sortBy('id')->first();
    }

    /**
     * Clear all currency cache for the current user.
     * Useful for manual cache invalidation or during testing/seeding.
     */
    public function clearCurrencyCache(?int $userId = null): void
    {
        $userId = $userId ?? auth()->user()?->id;
        
        if ($userId) {
            Cache::forget("currencies_user_{$userId}");
        }
    }

    /**
     * Get the latest exchange rate for a given currency from a map of rates.
     *
     * This method retrieves the latest exchange rate for a specified currency
     * from a pre-processed map of rates. The map contains average rates by month
     * for various currencies. The method returns the first rate that is less than
     * or equal to the given date.
     *
     * @param int|null $currencyId The ID of the currency for which to get the rate.
     * @param Carbon $date The date for which to get the latest rate.
     * @param array $allRatesMap A map of all rates, indexed by currency ID and date.
     * @param int $baseCurrencyID The ID of the base currency, for which we look the rate for.
     * @return float|null The latest rate for the given currency, or null if not found.
     */
    public function getLatestRateFromMap(?int $currencyId, Carbon $date, array $allRatesMap, int $baseCurrencyID): ?float
    {
        // If the currency is the base currency or not present in the rates map, return null
        if (
            $currencyId === null ||
            $currencyId === $baseCurrencyID ||
            !array_key_exists($currencyId, $allRatesMap)
        ) {
            return null;
        }

        // Iterate over the rates for the given currency
        foreach ($allRatesMap[$currencyId] as $rateDate => $rate) {
            $rateDateCarbon = Carbon::parse($rateDate);
            // Return the first rate that is less than or equal to the given date
            if ($rateDateCarbon->lte($date)) {
                return $rate;
            }
        }

        // If no rate is found, return null
        return null;
    }
}
