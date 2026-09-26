<?php

namespace App\Services;

use Illuminate\Support\Carbon;

/**
 * A small, standalone compounding calculation - deliberately independent of
 * RecurrenceRuleService's calendar-occurrence math (FR-5 decides which periods are active;
 * this decides what multiplier applies to a given period). The same calculation is expected to
 * be reused for investment price growth / currency drift assumptions later - see
 * future-directions.md - so it must not be coupled to TransactionSchedule or Budget.
 */
class InflationCalculator
{
    /**
     * Compound `$amount` at a flat `$ratePercent`% annual rate, once per calendar-year boundary
     * crossed between the year of `$startDate` and the year of `$targetDate`.
     *
     * The amount for the start year is the base amount unchanged; the moment a projection
     * crosses into the next calendar year - even if `$startDate` was December 31st, with almost
     * none of the start year elapsed - it compounds by (1 + rate / 100), and again at each
     * subsequent January 1st. A null/zero rate is a no-op.
     */
    public function applyAnnualRate(
        float $amount,
        ?float $ratePercent,
        Carbon $startDate,
        Carbon $targetDate,
    ): float {
        if (!$ratePercent) {
            return $amount;
        }

        $yearsElapsed = $targetDate->year - $startDate->year;

        if ($yearsElapsed <= 0) {
            return $amount;
        }

        return $amount * (1 + $ratePercent / 100) ** $yearsElapsed;
    }
}
