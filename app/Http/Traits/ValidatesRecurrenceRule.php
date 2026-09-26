<?php

namespace App\Http\Traits;

use App\Services\RecurrenceRuleService;
use Closure;
use Exception;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * Shared by_day/by_month/month-end-pattern validation rules for anything that validates a
 * frequency/interval/start_date/end_date/count(/by_day/by_month/days_before_month_end/
 * last_business_day_of_month) recurrence rule (TransactionRequest's schedule_config,
 * BudgetRequest) - see RecurrenceRuleService, which both sides ultimately feed into.
 *
 * These fields are always validated as separate request inputs - the request/response contract
 * never changes even though the model storing them collapses to a single `rrule` column (see
 * App\Models\Concerns\HasRecurrenceRule).
 */
trait ValidatesRecurrenceRule
{
    /**
     * A rule spanning more periods than this makes every RecurrenceRuleService call that
     * resolves an occurrence relative to "today" (isActive(), getNextInstance(), catch-up)
     * measurably slow - reproduced at ~4s/call for a DAILY rule with a centuries-old start_date.
     * 2000 periods comfortably covers realistic long-lived rules (daily for ~5 years, weekly for
     * ~38 years, monthly for 166 years, yearly effectively unlimited) while keeping every
     * RecurrenceRuleService call in the tens-of-milliseconds range.
     */
    private const int MAX_RECURRENCE_PERIODS = 2000;

    /**
     * Rejects a start_date whose distance from today, at the given frequency/interval, would
     * make every later RecurrenceRuleService call on this rule expensive - see
     * MAX_RECURRENCE_PERIODS. Attached to start_date since that's the field whose value actually
     * drives the cost; frequency/interval are read from sibling inputs the same way
     * nextDateOccursOnRule() reads its siblings.
     *
     * frequency/interval are still separate request fields (the request contract doesn't change
     * for this addendum) - only a minimal `FREQ=...;INTERVAL=...` fragment is assembled here to
     * feed RecurrenceRuleService::estimatePeriodsBetween()'s single-rrule-string signature.
     */
    private function maxRecurrencePeriodsRule(string $frequencyField, string $intervalField): Closure
    {
        return function ($attribute, $value, $fail) use ($frequencyField, $intervalField) {
            if (!$value) {
                return;
            }

            $frequency = $this->input($frequencyField);
            if (!$frequency) {
                return;
            }

            try {
                $startDate = Carbon::parse($value);
            } catch (Exception) {
                return; // Already caught by the 'date' rule.
            }

            $interval = (int) ($this->input($intervalField) ?: 1);

            $periods = app(RecurrenceRuleService::class)->estimatePeriodsBetween(
                $startDate,
                "FREQ={$frequency};INTERVAL={$interval}",
                Carbon::now(),
            );

            if ($periods > self::MAX_RECURRENCE_PERIODS) {
                $fail(__(
                    'This recurrence pattern spans too many periods (:count) to process. Pick a more recent start date or a less frequent recurrence.',
                    ['count' => $periods]
                ));
            }
        };
    }

    /**
     * Whether any of the three mutually-exclusive month-scoped patterns (ordinal by_day,
     * days-before-month-end, last-business-day-of-month) is set on this request.
     */
    private function hasMonthScopedPattern(
        string $byDayField,
        string $daysBeforeMonthEndField,
        string $lastBusinessDayField,
    ): bool {
        return (bool) $this->input($byDayField)
            || $this->input($daysBeforeMonthEndField) !== null
            || $this->boolean($lastBusinessDayField);
    }

    /**
     * Ordinal-weekday BYDAY rule (e.g. "1WE", "-1FR"), only meaningful for
     * MONTHLY/YEARLY frequencies. Mutually exclusive with the two month-end patterns below.
     */
    private function byDayRule(
        string $frequencyField,
        string $daysBeforeMonthEndField,
        string $lastBusinessDayField,
    ): array {
        return [
            'nullable',
            'string',
            'regex:/^(-?[1-4])(MO|TU|WE|TH|FR|SA|SU)$/',
            Rule::prohibitedIf(fn () => $this->input($daysBeforeMonthEndField) !== null
                || $this->boolean($lastBusinessDayField)),
            function ($attribute, $value, $fail) use ($frequencyField) {
                if ($value && !in_array($this->input($frequencyField), ['MONTHLY', 'YEARLY'], true)) {
                    $fail(__('Day-of-week recurrence requires a monthly or yearly frequency.'));
                }
            },
        ];
    }

    /**
     * "N days before month end" (0-27, e.g. 0 = the last day of the month), only meaningful for
     * MONTHLY/YEARLY frequencies. Mutually exclusive with by_day and last_business_day_of_month.
     */
    private function daysBeforeMonthEndRule(
        string $frequencyField,
        string $byDayField,
        string $lastBusinessDayField,
    ): array {
        return [
            'nullable',
            'integer',
            'between:0,27',
            Rule::prohibitedIf(fn () => (bool) $this->input($byDayField) || $this->boolean($lastBusinessDayField)),
            function ($attribute, $value, $fail) use ($frequencyField) {
                if ($value !== null && !in_array($this->input($frequencyField), ['MONTHLY', 'YEARLY'], true)) {
                    $fail(__('Days before month end recurrence requires a monthly or yearly frequency.'));
                }
            },
        ];
    }

    /**
     * "Last working day of the month" (weekday-only, no holiday calendar), only meaningful for
     * MONTHLY/YEARLY frequencies. Mutually exclusive with by_day and days_before_month_end.
     */
    private function lastBusinessDayOfMonthRule(
        string $frequencyField,
        string $byDayField,
        string $daysBeforeMonthEndField,
    ): array {
        return [
            'nullable',
            'boolean',
            Rule::prohibitedIf(fn () => (bool) $this->input($byDayField)
                || $this->input($daysBeforeMonthEndField) !== null),
            function ($attribute, $value, $fail) use ($frequencyField) {
                if ($value && !in_array($this->input($frequencyField), ['MONTHLY', 'YEARLY'], true)) {
                    $fail(__('Last business day of month recurrence requires a monthly or yearly frequency.'));
                }
            },
        ];
    }

    /**
     * Month (1-12) pinning a YEARLY month-scoped pattern (ordinal by_day or either month-end
     * pattern) to a specific month, e.g. "last Friday of November" or "5 days before the end of
     * March". Required whenever a YEARLY rule has one of those patterns set, since
     * RecurrenceRuleService/Recurr would otherwise apply it across the whole year rather than
     * per month.
     */
    private function byMonthRule(
        string $frequencyField,
        string $byDayField,
        string $daysBeforeMonthEndField,
        string $lastBusinessDayField,
    ): array {
        $hasPattern = fn () => $this->hasMonthScopedPattern($byDayField, $daysBeforeMonthEndField, $lastBusinessDayField);

        return [
            'nullable',
            'integer',
            'between:1,12',
            // A plain closure is skipped by the validator when the field is null and
            // 'nullable' is present, so the "required" direction needs an implicit
            // rule (Rule::requiredIf isn't skipped) rather than a closure fail().
            Rule::requiredIf(fn () => $this->input($frequencyField) === 'YEARLY' && $hasPattern()),
            // Reject the inverse too: a YEARLY rule without a month-scoped pattern would
            // silently ignore by_month rather than use it.
            Rule::prohibitedIf(fn () => $this->input($frequencyField) === 'YEARLY' && !$hasPattern()),
            function ($attribute, $value, $fail) use ($frequencyField) {
                if ($value && $this->input($frequencyField) !== 'YEARLY') {
                    $fail(__('Month only applies to yearly day-of-week recurrence.'));
                }
            },
        ];
    }
}
