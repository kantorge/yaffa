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
    /** Selectable waterfall groups; the cache key embeds the normalized (sorted) selection. */
    public const array GROUPS = ['income', 'standard', 'trades'];

    /** Groups behind the legacy {transactionType} route segment, kept for existing API consumers. */
    public const array LEGACY_TYPE_GROUPS = [
        'standard' => ['standard'],
        'investment' => ['income'],
        'all' => ['standard', 'income'],
    ];

    private const array DATA_TYPES = ['budget', 'result', 'all'];

    /**
     * @param  array<int, string>  $groups
     * @return string Sorted, de-duplicated, comma-joined groups
     */
    public static function normalizeGroups(array $groups): string
    {
        $groups = array_values(array_unique($groups));
        sort($groups);

        return implode(',', $groups);
    }

    public static function key(int $userId, string $groups, string $dataType, int $year, ?int $month): string
    {
        return sprintf(
            'categoryWaterfall_v1_user_%d_ver_%d_%s_%s_%d_%s',
            $userId,
            self::version($userId),
            $groups,
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
     * Forget every group-selection/dataType cache entry (both the year-only and the
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

        // Every non-empty subset of GROUPS (bitmask 1..2^n-1)
        $count = count(self::GROUPS);
        for ($mask = 1; $mask < (1 << $count); $mask++) {
            $groups = [];
            foreach (self::GROUPS as $i => $group) {
                if ($mask & (1 << $i)) {
                    $groups[] = $group;
                }
            }
            $groups = self::normalizeGroups($groups);

            foreach (self::DATA_TYPES as $dataType) {
                Cache::forget(self::key($userId, $groups, $dataType, $date->year, null));
                Cache::forget(self::key($userId, $groups, $dataType, $date->year, $date->month));
            }
        }
    }
}
