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
            'categoryWaterfall_v1_user_%d_ver_%d_%s_%s_%d_%s',
            $userId,
            self::version($userId),
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
     * Invalidate every cached waterfall entry for the user (all years/months/types), for
     * changes that aren't scoped to one transaction date - e.g. a Category rename, reparent,
     * or delete, which can affect every period's rendering.
     *
     * Bumps a per-user version embedded in key() instead of enumerating every possible
     * year/month key: old entries become unreachable and simply expire via ttl().
     */
    public static function forgetAllForUser(int $userId): void
    {
        Cache::increment(self::versionKey($userId));
    }

    private static function version(int $userId): int
    {
        return (int) Cache::get(self::versionKey($userId), 1);
    }

    private static function versionKey(int $userId): string
    {
        return 'categoryWaterfall_version_user_' . $userId;
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
