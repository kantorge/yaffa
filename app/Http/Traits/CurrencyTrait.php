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
     * Load a collection for all currencies, with an average rate by month
     *
     * @return Collection
     */
    public function allCurrencyRatesByMonth(): Collection
    {
        $baseCurrency = $this->getBaseCurrency();
        if (!$baseCurrency) {
            return new Collection();
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
                // Rates are retrieved only towards the base currency
                ->where('to_id', '=', $baseCurrency->id)
                ->groupBy(
                    DB::raw('SUBDATE(`date`, (DAY(`date`)-1))'),
                    'from_id'
                )
                ->get();

            $rates->transform(function ($rate) {
                $rate->date_from = Carbon::parse($rate->month);

                return $rate;
            });

            return $rates;
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
}
