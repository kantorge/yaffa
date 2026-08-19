<?php

namespace App\Http\Traits;

use App\Services\RecurrenceRuleService;
use Closure;
use Exception;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * Shared by_day/by_month validation rules for anything that validates a
 * frequency/interval/start_date/end_date/count(/by_day/by_month) recurrence
 * rule (TransactionRequest's schedule_config, BudgetRequest) - see
 * RecurrenceRuleService, which both sides ultimately feed into.
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
                $frequency,
                $interval,
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
     * Ordinal-weekday BYDAY rule (e.g. "1WE", "-1FR"), only meaningful for
     * MONTHLY/YEARLY frequencies.
     */
    private function byDayRule(string $frequencyField): array
    {
        return [
            'nullable',
            'string',
            'regex:/^(-?[1-4])(MO|TU|WE|TH|FR|SA|SU)$/',
            function ($attribute, $value, $fail) use ($frequencyField) {
                if ($value && !in_array($this->input($frequencyField), ['MONTHLY', 'YEARLY'], true)) {
                    $fail(__('Day-of-week recurrence requires a monthly or yearly frequency.'));
                }
            },
        ];
    }

    /**
     * Month (1-12) pinning a YEARLY ordinal-weekday rule to a specific month,
     * e.g. "last Friday of November". Required whenever a YEARLY by_day is
     * set, since recurr resolves an unscoped YEARLY BYDAY across the whole
     * year rather than per month.
     */
    private function byMonthRule(string $frequencyField, string $byDayField): array
    {
        return [
            'nullable',
            'integer',
            'between:1,12',
            // A plain closure is skipped by the validator when the field is null and
            // 'nullable' is present, so the "required" direction needs an implicit
            // rule (Rule::requiredIf isn't skipped) rather than a closure fail().
            Rule::requiredIf(fn () => $this->input($frequencyField) === 'YEARLY' && (bool) $this->input($byDayField)),
            // Reject the inverse too: RecurrenceRuleService only applies by_month when
            // by_day is also set, so a YEARLY rule without a by_day would silently
            // ignore by_month rather than use it.
            Rule::prohibitedIf(fn () => $this->input($frequencyField) === 'YEARLY' && !$this->input($byDayField)),
            function ($attribute, $value, $fail) use ($frequencyField) {
                if ($value && $this->input($frequencyField) !== 'YEARLY') {
                    $fail(__('Month only applies to yearly day-of-week recurrence.'));
                }
            },
        ];
    }
}
