<?php

namespace App\Http\Traits;

use App\Models\Currency;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

trait CurrencyTrait
{
    /**
     * Load a collection for all currencies, with an average rate by month
     *
     * @param  bool $onlyToBaseCurrency
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function allCurrencyRatesByMonth(bool $onlyToBaseCurrency = true): Collection
    {
        return DB::table('currency_rates')
            ->select(
                DB::raw('SUBDATE(`date`, (day(`date`)-1)) AS `month`'),
                'from_id',
                'to_id',
                DB::raw('avg(rate) as rate')
            )
            ->when($onlyToBaseCurrency, function ($query) {
                $query->where('to_id', '=', $this->getBaseCurrency()->id);
            })
            ->groupBy(DB::raw('SUBDATE(`date`, (day(`date`)-1))'))
            ->groupBy('from_id')
            ->groupBy('to_id')
            ->get();
    }

    /**
     * Get base currency, which is marked as base, or the first currency entered.
     *
     * @return App\Models\Currency;
     */
    public function getBaseCurrency(): Currency
    {
        return Currency::where('base', 1)->firstOr(function () {
            return Currency::orderBy('id')->firstOr(function () {
                //TODO: better handling of missing currencies. E.g., should there be a default currency with migration?
                return null;
            });
        });
    }
}
