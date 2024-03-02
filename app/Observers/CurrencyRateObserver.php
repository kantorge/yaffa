<?php

namespace App\Observers;

use App\Models\CurrencyRate;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

class CurrencyRateObserver
{
    protected function invalidateMonthlyCurrencyRateCache(User $user): void
    {
        $cacheKey = "allCurrencyRatesByMonth_forUser_{$user->id}";

        Cache::forget($cacheKey);
    }

    /**
     * Handle the CurrencyRate "created" event.
     */
    public function created(CurrencyRate $currencyRate): void
    {
        // Invalidate the cache for the monthly rates for this user
        $this->invalidateMonthlyCurrencyRateCache($currencyRate->currencyFrom->user);
    }

    /**
     * Handle the CurrencyRate "updated" event.
     */
    public function updated(CurrencyRate $currencyRate): void
    {
        // Invalidate the cache for the monthly rates for this user
        $this->invalidateMonthlyCurrencyRateCache($currencyRate->currencyFrom->user);
    }

    /**
     * Handle the CurrencyRate "deleted" event.
     */
    public function deleted(CurrencyRate $currencyRate): void
    {
        // Invalidate the cache for the monthly rates for this user
        $this->invalidateMonthlyCurrencyRateCache($currencyRate->currencyFrom->user);
    }
}
