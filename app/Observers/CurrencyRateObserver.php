<?php

namespace App\Observers;

use App\Http\Traits\CurrencyTrait;
use App\Models\CurrencyRate;
use App\Models\Currency;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

class CurrencyRateObserver
{
    use CurrencyTrait;

    protected function invalidateMonthlyCurrencyRateCache(User $user): void
    {
        Cache::forget($this->getAllCurrencyRatesByMonthCacheKey($user->id));
    }

    /**
     * Handle the CurrencyRate "created" event.
     */
    public function created(CurrencyRate $currencyRate): void
    {
        $currencyFrom = $currencyRate->currencyFrom;
        if (! $currencyFrom instanceof Currency) {
            return;
        }

        // Invalidate the cache for the monthly rates for this user
        $this->invalidateMonthlyCurrencyRateCache($currencyFrom->user);
    }

    /**
     * Handle the CurrencyRate "updated" event.
     */
    public function updated(CurrencyRate $currencyRate): void
    {
        $currencyFrom = $currencyRate->currencyFrom;
        if (! $currencyFrom instanceof Currency) {
            return;
        }

        // Invalidate the cache for the monthly rates for this user
        $this->invalidateMonthlyCurrencyRateCache($currencyFrom->user);
    }

    /**
     * Handle the CurrencyRate "deleted" event.
     */
    public function deleted(CurrencyRate $currencyRate): void
    {
        $currencyFrom = $currencyRate->currencyFrom;
        if (! $currencyFrom instanceof Currency) {
            return;
        }

        // Invalidate the cache for the monthly rates for this user
        $this->invalidateMonthlyCurrencyRateCache($currencyFrom->user);
    }
}
