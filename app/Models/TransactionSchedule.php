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
use Recurr\Rule;
use Recurr\Transformer\ArrayTransformer;
use Recurr\Transformer\ArrayTransformerConfig;
use Recurr\Transformer\Constraint\AfterConstraint;

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
        'automatic_recording' => 'boolean'
    ];

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function getNextInstance()
    {
        $constraint = new AfterConstraint(new DateTime($this->next_date), false);
        $rule = new Rule();

        $rule->setStartDate(new DateTime($this->start_date));

        if ($this->end_date) {
            $rule->setUntil(new DateTime($this->end_date));
        }

        $rule->setFreq($this->frequency);

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

        $recurrence = $transformer->transform($rule, $constraint);

        if ($recurrence->count() === 0) {
            return null;
        }

        return $recurrence[0]->getStart();
    }

    public function skipNextInstance(): bool
    {
        $this->next_date = $this->getNextInstance();

        return $this->save();
    }
}
