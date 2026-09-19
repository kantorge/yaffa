<?php

namespace App\Models\Concerns;

use Closure;
use DateTime;
use Exception;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Carbon;
use Recurr\Rule;

/**
 * Backs the discrete recurrence fields (frequency/interval/count/end_date/by_day/by_month, plus
 * the month-end patterns days_before_month_end/last_business_day_of_month) with a single, real
 * `rrule` (RFC 5545 RRULE string) column, so every RFC 5545 feature added after this one only
 * touches this trait's compose/decompose logic - never RecurrenceRuleService's method list or a
 * new database column.
 *
 * None of these fields are real columns or independently persisted: reading one decomposes it
 * out of the model's current `rrule` string via Recurr\Rule; setting one only records the pending
 * value (see $pendingRecurrenceFields) until composeRrule() (below) writes all of them (pending
 * values, falling back to whatever's already decomposed from the persisted `rrule`) into the one
 * string actually written to the `rrule` attribute.
 *
 * composeRrule() runs from two places, not from each individual field's `set` closure: fill()
 * (covers mass assignment - the constructor, fill(), forceFill(), and therefore create()/update())
 * and the `saving` hook (covers a direct `$model->frequency = ...` property write with no fill()
 * involved). Composing eagerly in fill() - not only in `saving` - matters beyond consistency: a
 * caller that inspects getDirty()/getOriginal() right after fill() but before save() (e.g.
 * TransactionApiController::updateStandard() building its change-log payload) needs `rrule`
 * to already reflect the new field values at that point, or every such change would look like a
 * no-op.
 */
trait HasRecurrenceRule
{
    /**
     * Values set on the virtual recurrence attributes since the model was loaded, not yet
     * composed into `rrule`. array_key_exists (not isset) distinguishes "explicitly set to
     * null/false" from "never touched this request", since the latter must still fall back to
     * decomposing the persisted `rrule` (partial updates only touch some fields).
     */
    protected array $pendingRecurrenceFields = [];

    protected static function bootHasRecurrenceRule(): void
    {
        static::saving(function ($model) {
            $model->composeRrule();
        });
    }

    public function fill(array $attributes)
    {
        $result = parent::fill($attributes);
        $this->composeRrule();

        return $result;
    }

    /**
     * A field combination that doesn't yet form a valid rule (e.g. mid-edit, or a frequency the
     * request validation hasn't rejected yet) is tolerated here rather than thrown: the pending
     * values are left in place (so they're still readable/re-fillable) and `rrule` simply isn't
     * updated this round. The model's own occurrence-consuming methods (isActive(), occursOn(),
     * etc.) rebuild via effectiveRrule() on every call and already handle this same failure via
     * their own try/catch - request-level validation (ValidatesRecurrenceRule) is the actual
     * guard against ever persisting an invalid combination.
     */
    private function composeRrule(): void
    {
        try {
            $rrule = $this->effectiveRrule();
        } catch (Exception) {
            return;
        }

        $this->attributes['rrule'] = $rrule;
        $this->pendingRecurrenceFields = [];
    }

    /**
     * The RRULE string this model's current field values (pending or already-persisted) compose
     * to - computed on demand rather than only read from the `rrule` column, since a schedule
     * built with `new TransactionSchedule([...])` and never saved (e.g.
     * TransactionRequest::nextDateOccursOnRule()) still needs a working rule for occurrence
     * checks.
     */
    public function effectiveRrule(): string
    {
        $frequency = $this->frequency;
        $interval = max((int) ($this->interval ?? 1), 1);
        $endDate = $this->end_date;
        $count = $this->count;
        $byDay = $this->by_day;
        $byMonth = $this->by_month;
        $daysBeforeMonthEnd = $this->days_before_month_end;
        $lastBusinessDay = $this->last_business_day_of_month;

        $rule = (new Rule())->setFreq($frequency)->setInterval($interval);

        if ($endDate) {
            $rule->setUntil(new DateTime(Carbon::parse($endDate)->toDateString()));
        }

        if ($count) {
            $rule->setCount($count);
        }

        if ($daysBeforeMonthEnd !== null) {
            $rule->setByMonthDay([-($daysBeforeMonthEnd + 1)]);

            if ($frequency === 'YEARLY' && $byMonth) {
                $rule->setByMonth([$byMonth]);
            }
        } elseif ($lastBusinessDay) {
            $rule->setByDay(['MO', 'TU', 'WE', 'TH', 'FR']);
            $rule->setBySetPosition([-1]);

            if ($frequency === 'YEARLY' && $byMonth) {
                $rule->setByMonth([$byMonth]);
            }
        } elseif ($byDay) {
            $rule->setByDay([$byDay]);

            if ($frequency === 'YEARLY' && $byMonth) {
                $rule->setByMonth([$byMonth]);
            }
        }

        return $rule->getString();
    }

    private function parsedRrule(): ?Rule
    {
        $rrule = $this->attributes['rrule'] ?? null;

        return $rrule ? Rule::createFromString($rrule) : null;
    }

    /**
     * Records a recurrence field's pending value - shared by every field's `set` closure below
     * and by App\Casts\RecurrenceCountCast (which backs `count` via a real cast class instead of
     * an auto-detected `count(): Attribute` method, since a same-named instance method would
     * make Eloquent's Model::__callStatic() route `Budget::count()`/`TransactionSchedule::count()`
     * - the aggregate query - to this accessor instead of the query builder).
     */
    public function setPendingRecurrenceField(string $field, mixed $value): void
    {
        $this->pendingRecurrenceFields[$field] = $value;
    }

    /**
     * Reads a recurrence field: the pending value if it was set since the model was loaded,
     * otherwise decomposed out of the persisted `rrule` via $decompose. Shared by
     * recurrenceAttribute() below and App\Casts\RecurrenceCountCast (see setPendingRecurrenceField()
     * for why `count` can't use the same auto-detected-method mechanism as the others).
     */
    public function getRecurrenceField(string $field, ?Closure $decompose = null): mixed
    {
        if (array_key_exists($field, $this->pendingRecurrenceFields)) {
            return $this->pendingRecurrenceFields[$field];
        }

        $rule = $this->parsedRrule();

        return $rule && $decompose ? $decompose($rule) : null;
    }

    /**
     * Builds a virtual Attribute for a recurrence field, wiring it to getRecurrenceField()/
     * setPendingRecurrenceField() above. Composing/writing the real `rrule` attribute happens
     * once per fill()/save() call (see composeRrule() above), not per-field.
     *
     * No native `: Attribute` return type here (despite always returning one) - Eloquent's
     * getAttributeMarkedMutatorMethods() auto-detects mutator accessors by reflecting every
     * method (any visibility) with that exact return type and invoking it with zero arguments;
     * matching it here would make Eloquent try to call this method itself as if it were an
     * accessor, and it isn't one - the required $field parameter would throw ArgumentCountError.
     *
     * @return Attribute
     */
    private function recurrenceAttribute(string $field, ?Closure $decompose = null)
    {
        return Attribute::make(
            get: fn () => $this->getRecurrenceField($field, $decompose),
            set: function ($value) use ($field) {
                $this->setPendingRecurrenceField($field, $value);

                return [];
            },
        );
    }

    protected function frequency(): Attribute
    {
        return $this->recurrenceAttribute('frequency', fn (Rule $rule) => $rule->getFreqAsText());
    }

    protected function interval(): Attribute
    {
        return $this->recurrenceAttribute('interval', fn (Rule $rule) => $rule->getInterval());
    }

    protected function endDate(): Attribute
    {
        return $this->recurrenceAttribute('end_date', function (Rule $rule) {
            $until = $rule->getUntil();

            return $until ? Carbon::instance($until) : null;
        });
    }

    /**
     * The ordinal-weekday pattern, e.g. "1WE" - null for a plain rule or for either month-end
     * pattern (which also uses BYDAY, but a fixed 5-weekday set, not a single ordinal one).
     */
    protected function byDay(): Attribute
    {
        return $this->recurrenceAttribute('by_day', function (Rule $rule) {
            $days = (array) $rule->getByDay();

            if (count($days) === 1 && preg_match('/^-?[1-4][A-Z]{2}$/', $days[0])) {
                return $days[0];
            }

            return null;
        });
    }

    protected function byMonth(): Attribute
    {
        return $this->recurrenceAttribute('by_month', function (Rule $rule) {
            $months = (array) $rule->getByMonth();

            return count($months) === 1 ? (int) $months[0] : null;
        });
    }

    /**
     * "N days before month end" - stored as a single negative BYMONTHDAY (-(N+1), so 0 => -1 =
     * the last day of the month).
     */
    protected function daysBeforeMonthEnd(): Attribute
    {
        return $this->recurrenceAttribute('days_before_month_end', function (Rule $rule) {
            $days = (array) $rule->getByMonthDay();

            if (count($days) === 1 && (int) $days[0] < 0) {
                return -((int) $days[0]) - 1;
            }

            return null;
        });
    }

    /**
     * "Last working day of the month" - the 5 weekdays plus BYSETPOS=-1 (the last matching one
     * within the period).
     */
    protected function lastBusinessDayOfMonth(): Attribute
    {
        return $this->recurrenceAttribute('last_business_day_of_month', function (Rule $rule) {
            $days = (array) $rule->getByDay();
            sort($days);

            // getBySetPosition() returns strings ("-1") when decoded from a parsed RRULE string,
            // but ints ([-1]) right after setBySetPosition([-1]) - normalize before comparing.
            $setPositions = array_map('intval', (array) $rule->getBySetPosition());

            return $setPositions === [-1] && $days === ['FR', 'MO', 'TH', 'TU', 'WE'];
        });
    }
}
