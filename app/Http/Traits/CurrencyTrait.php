<?php

namespace App\Http\Traits;

use App\Models\Currency;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

trait CurrencyTrait
{
    /**
     * Load an array for all currencies, with an average rate by month
     * As this data is not expected to change often, it is cached for a day
     *
     * @return array
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
     * Get base currency, which is marked as base, or the first currency entered.
     *
     * @return Currency|null;
     */
    public function getBaseCurrency(): ?Currency
    {
        if (!Auth::check()) {
            return null;
        }

        // Define the cache key for the current user
        $userId = auth()->user()->id;
        $cacheKey = "baseCurrency_forUser_{$userId}";

        // The base currency is not expected to change often, so it is cached for a month
        return Cache::remember($cacheKey, now()->addMonth(), function () {
            return Auth::user()
                ->currencies()
                ->where('base', 1)
                ->firstOr(
                    fn () => Auth::user()
                        ->currencies()
                        ->orderBy('id')
                        ->firstOr(fn () => null)
                );
        });
    }

    public function getLatestRateFromMap(int $currencyId, Carbon $date, array $allRatesMap, int $baseCurrencyID): ?float
    {
        if ($currencyId === $baseCurrencyID ||
            !array_key_exists($currencyId, $allRatesMap)) {
            return null;
        }

        foreach ($allRatesMap[$currencyId] as $rateDate => $rate) {
            $rateDateCarbon = Carbon::parse($rateDate);
            // We return the first rate from the map that is less than or equal to the given date
            if ($rateDateCarbon->lte($date)) {
                return $rate;
            }
        }

        return null;
    }
}
