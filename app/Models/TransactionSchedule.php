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
 */
class TransactionSchedule extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'transaction_schedules';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'transaction_id',
        'start_date',
        'next_date',
        'end_date',
        'frequency',
        'count',
        'interval',
        'inflation',
        'automatic_recording'
    ];

    protected $hidden = ['transaction_id'];

    protected $casts = [
        'next_date' => 'date',
        'start_date' => 'date',
        'end_date' => 'date',
        'automatic_recording' => 'boolean',
        'active' => 'boolean'
    ];

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
     *
     * @return bool
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
     * Determine if the schedule is determined to be active.
     *
     * The transaction schedule is active, if it has a next date defined. This is the case for not finished schedules.
     * Otherwise we need to process the rule and check if any of the occurrences are in the future.
     * This is the case for budgets or ended schedules.
     *
     * @return bool
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

        if ($recurrence->count() === 0) {
            return false;
        }

        $now = Carbon::now();

        foreach ($recurrence as $occurrence) {
            if ($occurrence->getStart() > $now) {
                return true;
            }
        }

        return false;
    }

    /**
     * Build the recurrence rule for the transaction schedule.
     *
     * @throws InvalidWeekday
     * @throws InvalidArgument
     * @throws Exception
     */
    private function getRecurrence(Carbon $afterDate = null): RecurrenceCollection
    {
        $rule = (new Rule())
            ->setStartDate(new DateTime($this->start_date))
            ->setFreq($this->frequency);

        if ($this->end_date) {
            $rule->setUntil(new DateTime($this->end_date));
        }

        if ($this->count) {
            $rule->setCount($this->count);
        }

        if ($this->interval) {
            $rule->setInterval($this->interval);
        }

        $transformer = new ArrayTransformer();
        $transformerConfig = new ArrayTransformerConfig();
        $transformerConfig->enableLastDayOfMonthFix();
        $transformer->setConfig($transformerConfig);

        $constraint = ($afterDate ? new AfterConstraint(new DateTime($afterDate), false) : null);

        return $transformer->transform($rule, $constraint);
    }
}
