<?php

namespace App\Models;

use Database\Factories\TransactionScheduleFactory;
use DateTime;
use Illuminate\Database\Eloquent\Model as Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Recurr\Exception\InvalidArgument;
use Recurr\Exception\InvalidWeekday;
use Recurr\RecurrenceCollection;
use Recurr\Rule;
use Recurr\Transformer\ArrayTransformer;
use Recurr\Transformer\ArrayTransformerConfig;
use Recurr\Transformer\Constraint\AfterConstraint;
use Recurr\Transformer\Constraint\BetweenConstraint;
use Exception;

/**
 * App\Models\TransactionSchedule
 *
 * @property int $id
 * @property int $transaction_id
 * @property Carbon $start_date
 * @property Carbon|null $next_date
 * @property Carbon|null $end_date
 * @property string $frequency
 * @property int $interval
 * @property string|null $by_day
 * @property int|null $by_month
 * @property int|null $count
 * @property float|null $inflation
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property bool $automatic_recording
 * @property bool $active
 * @property-read Transaction $transaction
 * @method static TransactionScheduleFactory factory(...$parameters)
 * @method static Builder|TransactionSchedule newModelQuery()
 * @method static Builder|TransactionSchedule newQuery()
 * @method static Builder|TransactionSchedule query()
 * @method static Builder|TransactionSchedule whereCount($value)
 * @method static Builder|TransactionSchedule whereCreatedAt($value)
 * @method static Builder|TransactionSchedule whereEndDate($value)
 * @method static Builder|TransactionSchedule whereFrequency($value)
 * @method static Builder|TransactionSchedule whereId($value)
 * @method static Builder|TransactionSchedule whereInflation($value)
 * @method static Builder|TransactionSchedule whereInterval($value)
 * @method static Builder|TransactionSchedule whereNextDate($value)
 * @method static Builder|TransactionSchedule whereStartDate($value)
 * @method static Builder|TransactionSchedule whereTransactionId($value)
 * @method static Builder|TransactionSchedule whereUpdatedAt($value)
 * @mixin Eloquent
 * @method static Builder<static>|TransactionSchedule whereActive($value)
 * @method static Builder<static>|TransactionSchedule whereAutomaticRecording($value)
 * @mixin \Eloquent
 */
class TransactionSchedule extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'transaction_id',
        'start_date',
        'next_date',
        'end_date',
        'frequency',
        'count',
        'interval',
        'by_day',
        'by_month',
        'inflation',
        'automatic_recording'
    ];

    protected $hidden = ['transaction_id'];

    protected function casts(): array
    {
        return [
            'next_date' => 'date',
            'start_date' => 'date',
            'end_date' => 'date',
            'by_month' => 'integer',
            'automatic_recording' => 'boolean',
            'active' => 'boolean'
        ];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    // Define closures for creating and updating a schedule, so that the active flag can be set
    protected static function booted(): void
    {
        static::creating(function (TransactionSchedule $schedule) {
            $schedule->active = $schedule->isActive();
        });

        static::updating(function (TransactionSchedule $schedule) {
            $schedule->active = $schedule->isActive();
        });
    }

    /**
     * @throws InvalidWeekday
     * @throws InvalidArgument
     * @throws Exception
     */
    public function getNextInstance()
    {
        if (!$this->next_date) {
            return null;
        }

        $recurrence = $this->getRecurrence($this->next_date);

        if ($recurrence->count() === 0) {
            return null;
        }

        return $recurrence[0]->getStart();
    }

    /**
     * Skip the next instance of this schedule, and return if it was successful.
     */
    public function skipNextInstance(): bool
    {
        try {
            $this->next_date = $this->getNextInstance();
        } catch (InvalidArgument|InvalidWeekday|Exception) {
            return false;
        }

        return $this->save();
    }

    /**
     * Safety cap for catchUpToDate()'s loop. This is a defense-in-depth guard against a
     * pathological/malformed schedule looping excessively within a single web request,
     * not a realistic business limit.
     */
    private const int MAX_CATCH_UP_ITERATIONS = 10000;

    /**
     * Advance next_date repeatedly - reusing the same mechanism as skipNextInstance()
     * ($this->next_date = $this->getNextInstance()) - until it is either null (schedule
     * exhausted) or on/after $date (defaults to today). Persists with a single save() at
     * the end, so the active flag - recalculated by the existing static::updating hook via
     * isActive() - is recomputed once from the final next_date, not once per skipped
     * occurrence.
     */
    public function catchUpToDate(?Carbon $date = null): bool
    {
        $target = ($date ?? Carbon::today())->copy()->startOfDay();
        $iterations = 0;

        try {
            while ($this->next_date !== null && $this->next_date->lt($target)) {
                if (++$iterations > self::MAX_CATCH_UP_ITERATIONS) {
                    return false;
                }

                $this->next_date = $this->getNextInstance();
            }
        } catch (InvalidArgument|InvalidWeekday|Exception) {
            return false;
        }

        return $this->save();
    }

    /**
     * Determine if the schedule is considered to be active.
     *
     * The transaction schedule is active, if it has a next date defined. This is the case for not finished schedules.
     * Otherwise we need to process the rule and check if any of the occurrences are in the future.
     * This is the case for budgets or ended schedules.
     */
    public function isActive(): bool
    {
        if ($this->next_date) {
            return true;
        }

        try {
            $recurrence = $this->getRecurrence(Carbon::now());
        } catch (InvalidArgument|InvalidWeekday|Exception) {
            // TODO: somehow the user should be notified about this error
            return false;
        }

        return $recurrence->count() > 0;
    }

    /**
     * Build the Recurr rule from this schedule's attributes, without any constraint applied.
     *
     * @throws InvalidWeekday
     * @throws InvalidArgument
     */
    private function buildRule(): Rule
    {
        $rule = (new Rule())
            ->setStartDate(new DateTime($this->start_date->toDateString()))
            ->setFreq($this->frequency);

        if ($this->end_date) {
            $rule->setUntil(new DateTime($this->end_date->toDateString()));
        }

        if ($this->count) {
            $rule->setCount($this->count);
        }

        if ($this->interval) {
            $rule->setInterval($this->interval);
        }

        if ($this->by_day) {
            $rule->setByDay([$this->by_day]);

            if ($this->frequency === 'YEARLY' && $this->by_month) {
                $rule->setByMonth([$this->by_month]);
            }
        }

        return $rule;
    }

    /**
     * Build the recurrence rule for the transaction schedule.
     *
     * @throws InvalidWeekday
     * @throws InvalidArgument
     * @throws Exception
     */
    private function getRecurrence(Carbon|null $afterDate = null): RecurrenceCollection
    {
        $rule = $this->buildRule();

        $transformer = new ArrayTransformer();
        $transformerConfig = new ArrayTransformerConfig();
        $transformerConfig->enableLastDayOfMonthFix();
        $transformer->setConfig($transformerConfig);

        $constraint = ($afterDate ? new AfterConstraint(new DateTime($afterDate->toDateString()), false) : null);

        return $transformer->transform($rule, $constraint);
    }

    /**
     * Whether $date is a genuine occurrence of this schedule's recurrence rule.
     *
     * next_date is trusted verbatim wherever a real transaction gets materialized
     * (automatic recording via RecordScheduledTransactions, and the manual "enter"
     * flow both use it as-is for the new transaction's date) - unlike forecast/
     * calendar views, which always re-derive occurrences from the rule. This method
     * lets callers (validation) catch a next_date that was never actually produced
     * by the rule - e.g. left over from before a frequency/by_day change - before
     * it gets persisted and eventually recorded on the wrong day.
     *
     * @throws InvalidWeekday
     * @throws InvalidArgument
     * @throws Exception
     */
    public function occursOn(Carbon $date): bool
    {
        $rule = $this->buildRule();

        $transformer = new ArrayTransformer();
        $transformerConfig = new ArrayTransformerConfig();
        $transformerConfig->enableLastDayOfMonthFix();
        $transformer->setConfig($transformerConfig);

        $day = new DateTime($date->toDateString());
        $constraint = new BetweenConstraint($day, $day, true);

        return $transformer->transform($rule, $constraint)->count() > 0;
    }
}
