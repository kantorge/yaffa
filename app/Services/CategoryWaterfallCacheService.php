<?php

namespace App\Services;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;

/**
 * Caches ReportApiController::getCategoryWaterfallData() results, since the underlying
 * transaction queries are identical on every load for a given (user, filters, period) and
 * closed months never change. Invalidated by ProcessTransaction{Created,Updated,Deleted}.
 */
class CategoryWaterfallCacheService
{
    private const array TRANSACTION_TYPES = ['standard', 'investment', 'all'];
    private const array DATA_TYPES = ['budget', 'result', 'all'];

    public static function key(int $userId, string $transactionType, string $dataType, int $year, ?int $month): string
    {
        return sprintf(
            'categoryWaterfall_v1_user_%d_%s_%s_%d_%s',
            $userId,
            $transactionType,
            $dataType,
            $year,
            $month ?? 'all'
        );
    }

    public static function ttl(): CarbonInterface
    {
        return now()->addDays(7);
    }

    /**
     * Forget every transactionType/dataType cache entry (both the year-only and the
     * year+month entry) covering the given transaction date.
     *
     * $date is null for schedule template transactions (Transaction::date is cleared
     * while schedule=true) - the waterfall query excludes those (`where('schedule', false)`),
     * so there is nothing to invalidate.
     */
    public static function forgetForDate(int $userId, ?CarbonInterface $date): void
    {
        if ($date === null) {
            return;
        }

        foreach (self::TRANSACTION_TYPES as $transactionType) {
            foreach (self::DATA_TYPES as $dataType) {
                Cache::forget(self::key($userId, $transactionType, $dataType, $date->year, null));
                Cache::forget(self::key($userId, $transactionType, $dataType, $date->year, $date->month));
            }
        }
    }
}
