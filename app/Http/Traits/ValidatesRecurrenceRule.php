<?php

namespace App\Http\Traits;

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
