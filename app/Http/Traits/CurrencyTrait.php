<?php

namespace App\Http\Traits;

use App\Models\Currency;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

trait CurrencyTrait
{
    /**
     * Load a collection for all currencies, with an average rate by month
     *
     * @param bool $withCarbonDates
     * @param bool $onlyToBaseCurrency
     * @return Collection
     */
    public function allCurrencyRatesByMonth(bool $withCarbonDates = true, bool $onlyToBaseCurrency = true): Collection
    {
        $baseCurrency = $this->getBaseCurrency();
        if (! $baseCurrency) {
            return new Collection();
        }

        $rates = DB::table('currency_rates')
            ->select(
                DB::raw('SUBDATE(`date`, (day(`date`)-1)) AS `month`'),
                'from_id',
                'to_id',
                DB::raw('avg(rate) as rate')
            )
            ->when($onlyToBaseCurrency, function ($query) use ($baseCurrency) {
                $query->where('to_id', '=', $baseCurrency->id);
            })
            ->groupBy(DB::raw('SUBDATE(`date`, (day(`date`)-1))'))
            ->groupBy('from_id')
            ->groupBy('to_id')
            ->get();

        if ($withCarbonDates) {
            $rates->transform(function ($rate) {
                $rate->date_from = Carbon::parse($rate->month);

                return $rate;
            });
        }

        return $rates;
    }

    /**
     * Get base currency, which is marked as base, or the first currency entered.
     *
     * @return Currency|null;
     */
    public function getBaseCurrency(): ?Currency
    {
        return Auth::user()
            ->currencies()
            ->where('base', 1)
            ->firstOr(fn () => Auth::user()->currencies()->orderBy('id')->firstOr(fn () => null));
    }
}
